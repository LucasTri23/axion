<?php
require_once __DIR__.'/_auth.php';require_once __DIR__.'/_layout.php';require_once __DIR__.'/_producao.php';
prodRequireManager();$db=db();$ready=prodReady($db);
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrfVerify();if(!$ready){http_response_code(409);exit('Módulo ainda não ativado.');}
    $id=(int)($_POST['id']??0);
    if($id)$db->prepare('UPDATE producao_notificacoes SET lida_em=COALESCE(lida_em,NOW()) WHERE id=? AND user_id=?')->execute([$id,$_SESSION['user_id']]);
    else $db->prepare('UPDATE producao_notificacoes SET lida_em=NOW() WHERE user_id=? AND lida_em IS NULL')->execute([$_SESSION['user_id']]);
    redirect('/admin/notificacoes.php');
}
$rows=[];$pages=1;$page=1;
if($ready){
    $s=$db->prepare('SELECT COUNT(*) FROM producao_notificacoes WHERE user_id=?');$s->execute([$_SESSION['user_id']]);$pages=max(1,(int)ceil((int)$s->fetchColumn()/50));$page=min($pages,max(1,(int)($_GET['page']??1)));$offset=($page-1)*50;
    $s=$db->prepare('SELECT n.id AS notificacao_id,n.lida_em,a.* FROM producao_notificacoes n JOIN producao_auditoria a ON a.id=n.auditoria_id WHERE n.user_id=? ORDER BY n.id DESC LIMIT 50 OFFSET '.$offset);$s->execute([$_SESSION['user_id']]);$rows=$s->fetchAll();
}
layout_start('Notificações de produção','notificacoes');
?>
<div class="card"><p>Alterações de peças, importações e documentos. Cada notificação registra quem fez a alteração; o histórico completo permanece na OS.</p><?php if($ready): ?><form method="POST" style="margin-top:12px"><?= csrfField() ?><button type="submit" class="btn btn-secondary btn-sm">Marcar todas como lidas</button></form><?php endif ?></div>
<?php foreach($rows as $row): ?><div class="card"><div class="card-title"><?= $row['lida_em']?'':'● ' ?><?= e($row['login'].' — '.str_replace('_',' ',$row['acao'])) ?></div><p class="text-muted"><?= e($row['created_at']) ?> · OS #<?= (int)$row['os_id'] ?> · Peça #<?= (int)$row['peca_id'] ?></p><div style="display:flex;gap:12px;margin-top:12px"><a class="btn btn-secondary btn-sm" href="/admin/producao.php?os=<?= (int)$row['os_id'] ?><?= $row['peca_id']?'&edit='.(int)$row['peca_id']:'' ?>#historico">Conferir alteração</a><?php if(!$row['lida_em']): ?><form method="POST"><?= csrfField() ?><input type="hidden" name="id" value="<?= (int)$row['notificacao_id'] ?>"><button class="btn btn-ghost btn-sm">Marcar lida</button></form><?php endif ?></div></div><?php endforeach ?>
<?php if(!$rows): ?><div class="card">Nenhuma notificação por enquanto.</div><?php endif ?>
<div style="display:flex;gap:12px;align-items:center;margin-bottom:16px">Página <?= $page ?> de <?= $pages ?><?php if($page>1): ?><a class="btn btn-ghost btn-sm" href="?page=<?= $page-1 ?>">Mais recentes</a><?php endif ?><?php if($page<$pages): ?><a class="btn btn-ghost btn-sm" href="?page=<?= $page+1 ?>">Mais antigas</a><?php endif ?></div>
<?php layout_end(); ?>
