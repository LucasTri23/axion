<?php
require_once __DIR__.'/_auth.php';
require_once __DIR__.'/_layout.php';
$db=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrfVerify();
    $id=(int)($_POST['id']??0);
    $reviewed=($_POST['action']??'')==='reabrir'?0:1;
    $db->beginTransaction();
    try{
        $s=$db->prepare('SELECT * FROM centro_import_pendencias WHERE id=? FOR UPDATE');$s->execute([$id]);$old=$s->fetch();
        if($old){
            $db->prepare('UPDATE centro_import_pendencias SET resolvida=? WHERE id=?')->execute([$reviewed,$id]);
            auditLog('update','centro_import_pendencias',$id,['antes'=>$old,'conferida'=>$reviewed]);
        }
        $db->commit();
    }catch(Throwable $error){$db->rollBack();throw $error;}
    redirect('/admin/financeiro_pendencias.php?saved=1');
}
$all=($_GET['ver']??'')==='todas';
$rows=$db->query('SELECT * FROM centro_import_pendencias'.($all?'':' WHERE resolvida=0').' ORDER BY resolvida,id')->fetchAll();
$pending=(int)$db->query('SELECT COUNT(*) FROM centro_import_pendencias WHERE resolvida=0')->fetchColumn();
layout_start('Conferência da planilha','financeiro');$finTab='pendencias';require __DIR__.'/_financeiro_nav.php';
?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success">Conferência registrada na auditoria.</div><?php endif ?>
<div class="card"><div class="card-title">Conferência da carga de 22/10 — <?= $pending ?> pendências</div><p>Os dados que exigem interpretação foram preservados abaixo. Corrija o cadastro, período ou lançamento correspondente antes de marcar como conferido. Esta marcação registra a revisão e não altera valores automaticamente.</p><div class="btn-row" style="margin-top:16px"><a class="btn btn-secondary" href="?">Pendentes</a><a class="btn btn-ghost" href="?ver=todas">Todas as conferências</a><a class="btn btn-ghost" href="/admin/financeiro_funcionarios.php">Funcionários</a><a class="btn btn-ghost" href="/admin/financeiro_folha.php">Folha / competências</a></div></div>
<?php foreach($rows as $row):$item=json_decode($row['dados'],true); ?>
<details class="card"><summary style="cursor:pointer;font-weight:600"><?= $row['resolvida']?'✓ Conferido · ':'Pendente · ' ?>#<?= (int)$row['id'] ?> — <?= e(is_array($item)?($item['motivo']??$item['mensagem']??'Conferir dados de origem'):'Conferir dados de origem') ?></summary><p class="text-muted" style="margin-top:14px">Origem: <?= e($row['origem']) ?></p><pre style="white-space:pre-wrap;overflow-wrap:anywhere;font-size:13px;margin:16px 0"><?= e(json_encode($item,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre><form method="POST"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="action" value="<?= $row['resolvida']?'reabrir':'conferir' ?>"><button class="btn btn-secondary"><?= $row['resolvida']?'Reabrir conferência':'Marcar como conferido' ?></button></form></details>
<?php endforeach ?>
<?php if(!$rows): ?><div class="card">Nenhuma pendência nesta visualização.</div><?php endif ?>
<?php layout_end(); ?>
