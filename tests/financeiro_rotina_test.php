<?php
require_once __DIR__.'/../admin/_financeiro_recorrencias.php';
require_once __DIR__.'/../admin/_financeiro_rotina.php';
require_once __DIR__.'/../admin/_financeiro_filtros.php';
function checkRoutine(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
class RoutineFixturePDO extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {return parent::prepare(str_replace(' FOR UPDATE','',$query),$options);}
}
$db=new RoutineFixturePDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
@$db->sqliteCreateFunction('DATE_FORMAT',fn($date,$format)=>substr($date,0,7),2);
$failAudit=false;
function auditLog(string $a,string $t,int $id,array $detail=[]): void {global $db,$failAudit;if($failAudit)throw new RuntimeException('Falha de auditoria simulada');$db->prepare('INSERT INTO audit(acao,tabela,registro) VALUES(?,?,?)')->execute([$a,$t,$id]);}
$db->exec("PRAGMA foreign_keys=ON;
 CREATE TABLE centro_recorrencias(id INTEGER PRIMARY KEY,descricao TEXT,fornecedor TEXT,valor DECIMAL,dia INTEGER,inicio TEXT,fim TEXT,os TEXT,centro TEXT,ativo INTEGER);
 CREATE TABLE centro_lancamentos(id INTEGER PRIMARY KEY,tipo TEXT,descricao TEXT,cliente_fornecedor TEXT,os TEXT,centro TEXT,data_lancamento TEXT,vencimento TEXT,valor_entrada DECIMAL,valor_saida DECIMAL,situacao TEXT,observacao TEXT);
 CREATE TABLE centro_recorrencias_geradas(id INTEGER PRIMARY KEY,recorrencia_id INTEGER,competencia TEXT,lancamento_id INTEGER REFERENCES centro_lancamentos(id) ON DELETE SET NULL,UNIQUE(recorrencia_id,competencia));
 CREATE TABLE audit(acao TEXT,tabela TEXT,registro INTEGER);
 CREATE TABLE centro_funcionarios(id INTEGER PRIMARY KEY,nome TEXT);
 CREATE TABLE centro_folha_periodos(id INTEGER PRIMARY KEY,data_periodo TEXT,competencia TEXT);
 CREATE TABLE centro_folha_pagamentos(id INTEGER PRIMARY KEY,funcionario_id INTEGER,periodo_id INTEGER,valor DECIMAL,vale_transporte DECIMAL,valor_pago DECIMAL,situacao_pagamento TEXT);");
checkRoutine(recorrenciaVencimento('2026-02',31)==='2026-02-28','Fevereiro comum');
checkRoutine(recorrenciaVencimento('2028-02',31)==='2028-02-29','Fevereiro bissexto');
checkRoutine(recorrenciaVencimento('2026-04',31)==='2026-04-30','Mês de 30 dias');
$valid=['descricao'=>'Aluguel','valor'=>'1500.50','dia'=>'31','inicio'=>'2026-10','ativo'=>'1'];
checkRoutine(recorrenciaValidar($valid)['valor']==='1500.50','Validação de centavos');
foreach([['valor'=>'0'],['dia'=>'32'],['inicio'=>'2026-13'],['fim'=>'2026-09']] as $bad){try{recorrenciaValidar(array_replace($valid,$bad));throw new RuntimeException('Dados inválidos aceitos');}catch(InvalidArgumentException $expected){}}
$db->exec("INSERT INTO centro_recorrencias VALUES(1,'Aluguel','Imobiliária',1500.50,31,'2026-10',NULL,'','GERAL',1),(2,'Pausada','',20,10,'2026-01',NULL,'','GERAL',0),(3,'Encerrada','',30,10,'2026-01','2026-09','','GERAL',1),(4,'Futura','',40,10,'2027-01',NULL,'','GERAL',1)");
$rows=recorrenciasPrevia($db,'2026-10');checkRoutine(count($rows)===1,'Prévia deve respeitar vigência e pausa');
checkRoutine((int)$db->query('SELECT COUNT(*) FROM centro_lancamentos')->fetchColumn()===0,'Prévia não pode gravar');
try{recorrenciasGerar($db,'2026-10','assinatura-antiga','2026-10-22');throw new RuntimeException('Prévia antiga aceita');}catch(InvalidArgumentException $expected){}
$signature=recorrenciasAssinatura($rows,'2026-10');
$failAudit=true;
try{recorrenciasGerar($db,'2026-10',$signature,'2026-10-22');throw new LogicException('Falha não propagada');}catch(RuntimeException $expected){checkRoutine($expected->getMessage()==='Falha de auditoria simulada','Falha inesperada');}
checkRoutine((int)$db->query('SELECT COUNT(*) FROM centro_lancamentos')->fetchColumn()===0,'Rollback deve incluir lançamentos');
checkRoutine((int)$db->query('SELECT COUNT(*) FROM centro_recorrencias_geradas')->fetchColumn()===0,'Rollback deve incluir controle de geração');$failAudit=false;
checkRoutine(recorrenciasGerar($db,'2026-10',$signature,'2026-10-22')===1,'Geração única');
checkRoutine($db->query('SELECT situacao FROM centro_lancamentos')->fetchColumn()==='','Despesa gerada não pode vir paga');
checkRoutine((float)$db->query('SELECT valor_saida FROM centro_lancamentos')->fetchColumn()===1500.5,'Valor gerado incorreto');
try{recorrenciasGerar($db,'2026-10',$signature,'2026-10-22');throw new RuntimeException('Geração duplicada aceita');}catch(InvalidArgumentException $expected){}
$db->exec('DELETE FROM centro_lancamentos');checkRoutine(recorrenciasPrevia($db,'2026-10')===[],'Exclusão não pode liberar geração duplicada');
$db->exec("INSERT INTO centro_lancamentos(tipo,descricao,vencimento,valor_saida,valor_entrada,situacao) VALUES
 ('SAIDA','Vencida','2026-10-21',10,0,''),('SAIDA','Hoje','2026-10-22',20,0,''),('SAIDA','Próxima','2026-10-29',30,0,''),
 ('SAIDA','Muito futura','2026-10-30',40,0,''),('SAIDA','Paga','2026-10-20',50,0,' OK '),('SAIDA','Sem vencimento',NULL,60,0,''),('ENTRADA','Receber','2026-10-20',0,70,'');
 INSERT INTO centro_funcionarios VALUES(1,'Parcial'),(2,'A confirmar'),(3,'Pago'),(4,'Período futuro');
 INSERT INTO centro_folha_periodos VALUES(1,'2026-10-20','2026-10'),(2,'2026-10-30','2026-10');
 INSERT INTO centro_folha_pagamentos VALUES(1,1,1,100,10,50,'parcial'),(2,2,1,100,0,NULL,'a_confirmar'),(3,3,1,100,0,100,'pago'),(4,4,2,100,0,0,'pendente');");
$routine=financeiroRotina($db,'2026-10-22');
checkRoutine((int)$routine['totals']['vencidas']===1&&(float)$routine['totals']['valor_vencidas']===10.0,'Vencidas excluem quitadas/entradas/sem data');
checkRoutine((int)$routine['totals']['hoje']===1&&(float)$routine['totals']['valor_hoje']===20.0,'Contas de hoje');
checkRoutine((int)$routine['totals']['proximas']===1&&(float)$routine['totals']['valor_proximas']===30.0,'Limite exato de sete dias');
checkRoutine($routine['staff_count']===2,'Folha deve excluir integralmente pagos e períodos futuros');
checkRoutine(count($routine['accounts'])===3,'Lista de ações e totais devem coincidir');
[$where,$params]=financeiroWhere(financeiroFiltros(['status'=>'proximos','limite'=>'7']),'vencimento');
checkRoutine(str_contains($where,'vencimento <= ?')&&end($params)===(new DateTimeImmutable(financeiroHoje()))->modify('+7 days')->format('Y-m-d'),'Atalho deve manter limite de sete dias');
echo "PASS: recorrências, prévia sem gravação, vigência, centavos, rollback, duplicidades e resumo diário.\n";
