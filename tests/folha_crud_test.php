<?php
// Integração isolada: PDO SQLite temporário, sem ler configuração ou banco de produção.
if(($argv[1]??'')==='--worker'){
 class FixturePDO extends PDO {
  public function prepare(string $query,array $options=[]): PDOStatement|false {return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);}
 }
 $root=dirname(__DIR__);$fixture=new FixturePDO('sqlite:'.$argv[2]);$fixture->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$fixture->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
 @$fixture->sqliteCreateFunction('DATE_FORMAT',fn($date,$format)=>substr($date,0,7),2);
 function db(): PDO {global $fixture;return $fixture;}
 function csrfVerify(): void {if(($_POST['csrf']??'')!=='test-token')throw new RuntimeException('CSRF');}
 function csrfField(): string {return '<input type="hidden" name="csrf" value="test-token">';}
 function auditLog(string $a,string $t,int $id,array $details=[]): void {db()->prepare('INSERT INTO audit(action,table_name,record_id,details) VALUES(?,?,?,?)')->execute([$a,$t,$id,json_encode($details)]);}
 function redirect(string $url): never {echo 'REDIRECT '.$url;exit;}
 function e($s): string {return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
 function brl($v): string {return 'R$ '.number_format((float)$v,2,',','.');}
 function layout_start(string $title,string $page): void {
  global $root;
  preg_match('~<style>(.*?)</style>~s',file_get_contents($root.'/admin/_layout.php'),$m);
  echo '<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>'.$m[1].file_get_contents($root.'/css/funcionarios.css').'</style><body><main style="padding:24px;max-width:1450px;margin:auto">';
 }
 function layout_end(): void {echo '</main></body></html>';}
 $scenario=$argv[3];$_GET=['mes'=>'2026-10'];$_POST=[];$_SERVER['REQUEST_METHOD']='POST';
 $base=['csrf'=>'test-token','mes'=>'2026-10','funcionario_id'=>1];
 switch($scenario){
  case 'create':$_POST=$base+['action'=>'salvar','funcionario_id'=>0,'nome'=>'Pessoa Teste','cpf'=>'','telefone'=>'','pix_chave'=>'teste@example.test','banco_info'=>'Banco teste','ativo'=>'1'];$_POST['funcionario_id']=0;break;
  case 'edit':$_POST=$base+['action'=>'salvar','nome'=>'Pessoa Editada','cpf'=>'','telefone'=>'123','pix_chave'=>'pix-visivel','banco_info'=>'Conta teste','ativo'=>'1'];break;
  case 'partial':case 'overpay':case 'paid':$_POST=$base+['action'=>'pagamento','pagamento_id'=>1,'modo'=>'registrar','valor_pago'=>$scenario==='partial'?'50.00':($scenario==='paid'?'110.00':'111.00'),'data_pagamento'=>'2026-10-22'];break;
  case 'unknown':case 'pending':$_POST=$base+['action'=>'pagamento','pagamento_id'=>1,'modo'=>$scenario==='unknown'?'a_confirmar':'pendente'];break;
  case 'delete':case 'delete_bad':$_POST=$base+['action'=>'excluir','confirmar_exclusao'=>'1','confirmar_nome'=>$scenario==='delete'?'Pessoa Editada':'nome errado'];break;
  case 'list':case 'profile':$_SERVER['REQUEST_METHOD']='GET';if($scenario==='profile')$_GET['id']=1;break;
 }
 $source=file_get_contents($root.'/admin/financeiro_funcionarios.php');
 $source=str_replace(["require_once __DIR__.'/_auth.php';","require_once __DIR__.'/_layout.php';","require __DIR__.'/_financeiro_nav.php';"],'',$source);
 $source=str_replace("require_once __DIR__.'/_folha_pagamentos.php';","require_once ".var_export($root.'/admin/_folha_pagamentos.php',true).';',$source);
 eval(substr($source,5));exit;
}
function ensure(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$file=tempnam(sys_get_temp_dir(),'axion-folha-test-');$db=new PDO('sqlite:'.$file);
$db->exec("CREATE TABLE centro_funcionarios(id INTEGER PRIMARY KEY AUTOINCREMENT,nome TEXT,cpf TEXT,telefone TEXT,pix_chave TEXT,banco_info TEXT,ativo INTEGER DEFAULT 1);
 CREATE TABLE centro_folha_periodos(id INTEGER PRIMARY KEY,data_periodo TEXT,tipo TEXT,competencia TEXT);
 CREATE TABLE centro_folha_pagamentos(id INTEGER PRIMARY KEY,periodo_id INTEGER,funcionario_id INTEGER,valor DECIMAL,vale_transporte DECIMAL,observacao TEXT,situacao_pagamento TEXT,valor_pago DECIMAL,data_pagamento TEXT);
 CREATE TABLE audit(id INTEGER PRIMARY KEY,action TEXT,table_name TEXT,record_id INTEGER,details TEXT);
 INSERT INTO centro_folha_periodos VALUES(1,'2026-10-20','Adiantamento','2026-10');");
function runCase(string $scenario): string {
 global $file;
 $command=[PHP_BINARY,'-n','-d','extension_dir='.ini_get('extension_dir'),'-d','extension=pdo_sqlite',__FILE__,'--worker',$file,$scenario];
 $p=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);
 ensure($code===0,'Falha '.$scenario.': '.$error);return $output;
}
try{
 $created=runCase('create');ensure(str_starts_with(trim($created),'REDIRECT'),'Criação não redirecionou: '.$created);ensure((int)$db->query('SELECT COUNT(*) FROM centro_funcionarios')->fetchColumn()===1,'Cadastro não criado');
 runCase('edit');ensure($db->query('SELECT pix_chave FROM centro_funcionarios')->fetchColumn()==='pix-visivel','PIX não editado');
 $db->exec("INSERT INTO centro_folha_pagamentos VALUES(1,1,1,100,10,'Teste','a_confirmar',NULL,NULL)");
 runCase('partial');ensure($db->query('SELECT situacao_pagamento FROM centro_folha_pagamentos')->fetchColumn()==='parcial','Status parcial incorreto');
 runCase('overpay');ensure((float)$db->query('SELECT valor_pago FROM centro_folha_pagamentos')->fetchColumn()===50.0,'Pagamento acima do previsto não foi bloqueado');
 runCase('paid');ensure($db->query('SELECT situacao_pagamento FROM centro_folha_pagamentos')->fetchColumn()==='pago','Quitação não registrada');
 $profile=runCase('profile');ensure(str_contains($profile,'Pago confirmado')&&str_contains($profile,'110,00'),'Ficha não mostra quitação');
 file_put_contents(sys_get_temp_dir().'/axion-folha-profile-test.html',$profile);
 runCase('unknown');ensure($db->query('SELECT valor_pago FROM centro_folha_pagamentos')->fetchColumn()===null,'Volta para desconhecido não limpou valor');
 runCase('pending');ensure($db->query('SELECT situacao_pagamento FROM centro_folha_pagamentos')->fetchColumn()==='pendente','Pendente não registrado');
 $list=runCase('list');ensure(str_contains($list,'pix-visivel')&&str_contains($list,'Pendente'),'Lista não mostra PIX e situação');file_put_contents(sys_get_temp_dir().'/axion-folha-list-test.html',$list);
 runCase('delete_bad');ensure((int)$db->query('SELECT COUNT(*) FROM centro_funcionarios')->fetchColumn()===1,'Exclusão sem nome correto foi aceita');
 runCase('delete');ensure((int)$db->query('SELECT COUNT(*) FROM centro_funcionarios')->fetchColumn()===0,'Exclusão do cadastro falhou');ensure((int)$db->query('SELECT COUNT(*) FROM centro_folha_pagamentos')->fetchColumn()===0,'Exclusão dos lançamentos falhou');ensure((int)$db->query("SELECT COUNT(*) FROM audit WHERE action='delete'")->fetchColumn()===2,'Auditoria de exclusão incompleta');
 echo "PASS: CRUD, PIX visível, parcial/integral, bloqueio de excesso, reversão de confirmação e auditoria de exclusão em base isolada.\n";
}finally{$db=null;unlink($file);}
