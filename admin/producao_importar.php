<?php
require_once __DIR__.'/_auth.php';require_once __DIR__.'/_layout.php';require_once __DIR__.'/_producao.php';require_once __DIR__.'/_producao_xlsx.php';
prodRequireManager();$db=db();$os=(int)($_GET['os']??$_POST['os_id']??0);$error='';
if(!prodReady($db)){http_response_code(409);exit('Ative o módulo de produção primeiro.');}
$s=$db->prepare('SELECT * FROM producao_os WHERE id=?');$s->execute([$os]);$order=$s->fetch();if(!$order){http_response_code(404);exit('OS não encontrada.');}
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfVerify();
    try{
        if(($_POST['action']??'')==='preview') {
            $file=$_FILES['planilha']??[];if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>20*1024*1024||strtolower(pathinfo($file['name'],PATHINFO_EXTENSION))!=='xlsx')throw new InvalidArgumentException('Envie um XLSX de até 20 MB.');
            $result=prodLerXlsx($file['tmp_name']);$hash=hash_file('sha256',$file['tmp_name']);
            $logins=$db->query('SELECT u.username FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE COALESCE(p.ativo,1)=1')->fetchAll(PDO::FETCH_COLUMN);
            foreach($result['rows'] as $row)if(($row['responsavel']??'')!==''&&!in_array($row['responsavel'],$logins,true))$result['errors'][]='Linha '.$row['linha'].': responsável não corresponde a um login ativo: '.$row['responsavel'];
            $_SESSION['prod_import']=['os'=>$os,'sha'=>$hash,'nome'=>substr(basename($file['name']),0,255),'result'=>$result,'token'=>bin2hex(random_bytes(24)),'expires'=>time()+1800];
        }elseif(($_POST['action']??'')==='confirmar'){
            $pending=$_SESSION['prod_import']??null;
            if(!$pending||$pending['os']!==$os||$pending['expires']<time()||!hash_equals($pending['token'],$_POST['preview_token']??'')||$pending['result']['errors'])throw new InvalidArgumentException('Prévia expirada ou com erros. Envie a planilha novamente.');
            if(!isset($_POST['peso_confirmado']))throw new InvalidArgumentException('Confirme que o peso informado é o peso de cada peça.');
            $db->beginTransaction();$db->prepare('INSERT INTO producao_importacoes(os_id,sha256,nome,linhas,created_by) VALUES(?,?,?,?,?)')->execute([$os,$pending['sha'],$pending['nome'],count($pending['result']['rows']),$_SESSION['user_id']]);
            foreach($pending['result']['rows'] as $row){
                $s=$db->prepare('SELECT u.id FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id WHERE u.username=? AND COALESCE(p.ativo,1)=1');$s->execute([$row['responsavel']??'']);$responsavel=$s->fetchColumn();
                if(($row['responsavel']??'')!==''&&!$responsavel)throw new InvalidArgumentException('Responsável "'.$row['responsavel'].'" não encontrado na linha '.$row['linha'].'. Cadastre o login ou corrija a planilha.');
                $row['responsavel_id']=$responsavel?:null;$dados=prodPecaDados($db,$row);prodInserirPeca($db,$os,$dados,$row['status'],$row['etapas']);
            }
            prodAudit($db,'importar_planilha',$os,null,null,['arquivo'=>$pending['nome'],'sha256'=>$pending['sha'],'itens'=>count($pending['result']['rows'])]);$db->commit();unset($_SESSION['prod_import']);redirect('/admin/producao.php?os='.$os.'&saved=1');
        }else throw new InvalidArgumentException('Ação inválida.');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e instanceof InvalidArgumentException?$e->getMessage():(($e instanceof PDOException&&($e->errorInfo[1]??0)===1062)?'Este arquivo já foi importado nesta OS.':'Não foi possível importar. Nenhuma peça foi gravada.');error_log('[Axion importar] '.$e->getMessage());}
}
$pending=$_SESSION['prod_import']??null;$preview=$pending&&$pending['os']===$os&&$pending['expires']>=time()?$pending:null;
layout_start('Importar lista de fabricação','producao');
?>
<link rel="stylesheet" href="/css/producao.css"><a class="btn btn-ghost" href="/admin/producao.php?os=<?= $os ?>">← OS <?= e($order['numero']) ?></a>
<?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
<div class="card"><div class="card-title">Importar XLSX com conferência</div><p>Cabeçalhos: DATA, AVULSAS, DESCRIÇÃO DO MATERIAL, TAG, Nº DESENHO, QUANTIDADE, PESO, RESPONSÁVEL, Nº ROMANEIO, STATUS FABRICAÇÃO. Peso é unitário em kg. Responsável corresponde ao login cadastrado. GRD é aceito como alternativa à DATA. Etapas opcionais: PREP, MONT, SOLDA, ACAB, PINT, EXD.</p><p class="text-muted" style="margin-top:12px">O arquivo acrescenta peças à OS. Reimportar o mesmo arquivo é bloqueado. Fórmulas não são executadas; totais e avanço são recalculados. Alterações de listas já importadas devem ser feitas nas peças para preservar o histórico.</p><form method="POST" enctype="multipart/form-data" class="frow" style="margin-top:16px"><?= csrfField() ?><input type="hidden" name="action" value="preview"><input type="hidden" name="os_id" value="<?= $os ?>"><div class="fg"><label>Lista de fabricação (.xlsx)</label><input type="file" name="planilha" accept=".xlsx" required></div><div><button class="btn btn-secondary">Conferir antes de importar</button></div></form></div>
<?php if($preview):$result=$preview['result'];$grams=0;foreach($result['rows'] as $row)$grams+=prodPesoGramas($row['peso_unitario'])*(int)$row['quantidade']; ?>
<div class="card"><div class="card-title">Prévia · <?= count($result['rows']) ?> itens · <?= number_format($grams/1000,3,',','.') ?> kg</div><p>Confira peso unitário e quantidade: os modelos antigos podem conter peso total na coluna PESO. Nesse caso, ajuste o arquivo antes de importar.</p><?php foreach($result['errors'] as $message): ?><p class="alert alert-error"><?= e($message) ?></p><?php endforeach ?>
<div class="prod-scroll"><table><thead><tr><th>Linha</th><th>Data</th><th>Material</th><th>TAG / desenho</th><th>Qtd.</th><th>Peso unit.</th><th>Peso total</th><th>Responsável</th><th>Status / avanço</th></tr></thead><tbody><?php foreach($result['rows'] as $row): ?><tr><td><?= $row['linha'] ?></td><td><?= e($row['data']) ?></td><td><?= e($row['descricao']) ?></td><td><?= e($row['tag'].' / '.$row['desenho']) ?></td><td><?= (int)$row['quantidade'] ?></td><td><?= e($row['peso_unitario']) ?> kg</td><td><?= number_format(prodPesoGramas($row['peso_unitario'])*(int)$row['quantidade']/1000,3,',','.') ?> kg</td><td><?= e($row['responsavel']??'') ?></td><td><?= e(PROD_STATUS[$row['status']]) ?> · <?= number_format(prodAvanco($row['etapas']),2,',','.') ?>%</td></tr><?php endforeach ?></tbody></table></div>
<?php if(!$result['errors']): ?><form method="POST" style="margin-top:16px"><?= csrfField() ?><input type="hidden" name="action" value="confirmar"><input type="hidden" name="os_id" value="<?= $os ?>"><input type="hidden" name="preview_token" value="<?= e($preview['token']) ?>"><label><input type="checkbox" name="peso_confirmado" required> Conferi: PESO é unitário e QUANTIDADE está correta.</label><div style="margin-top:12px"><button class="btn btn-primary">Importar <?= count($result['rows']) ?> peças</button></div></form><?php else: ?><p>Corrija os erros e envie novamente. Nenhuma peça foi importada.</p><?php endif ?></div>
<?php endif;layout_end(); ?>
