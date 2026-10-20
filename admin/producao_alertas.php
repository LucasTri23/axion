<?php
require_once __DIR__.'/_auth.php';require_once __DIR__.'/_producao.php';
prodRequireManager();header('Content-Type: application/json; charset=UTF-8');header('Cache-Control: no-store');
if(!prodReady(db())){echo json_encode(['count'=>0,'latest'=>null]);exit;}
$s=db()->prepare('SELECT COUNT(*) FROM producao_notificacoes WHERE user_id=? AND lida_em IS NULL');$s->execute([$_SESSION['user_id']]);$count=(int)$s->fetchColumn();
$s=db()->prepare('SELECT n.id,a.login,a.acao,a.os_id,a.peca_id FROM producao_notificacoes n JOIN producao_auditoria a ON a.id=n.auditoria_id WHERE n.user_id=? AND n.lida_em IS NULL ORDER BY n.id DESC LIMIT 1');$s->execute([$_SESSION['user_id']]);
echo json_encode(['count'=>$count,'latest'=>$s->fetch()?:null],JSON_UNESCAPED_UNICODE);
