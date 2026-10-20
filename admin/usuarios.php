<?php
require_once __DIR__.'/_auth.php';
require_once __DIR__.'/_layout.php';
require_once __DIR__.'/_producao.php';
if(perfilAtual()!=='admin'){http_response_code(403);exit('Acesso exclusivo do administrador.');}
$db=db();$error='';$ready=prodReady($db);
if($_SERVER['REQUEST_METHOD']==='POST') {
    csrfVerify();
    if(!$ready){http_response_code(409);exit('Ative o módulo de produção primeiro.');}
    try {
        $id=(int)($_POST['id']??0);$nome=prodTexto($_POST,'username',50,true);
        $perfil=$_POST['perfil']??'';$ativo=isset($_POST['ativo'])?1:0;$senha=$_POST['senha']??'';
        if(!in_array($perfil,['admin','pcp','ajudante'],true))throw new InvalidArgumentException('Perfil inválido.');
        if($id===(int)$_SESSION['user_id']&&($perfil!=='admin'||!$ativo))throw new InvalidArgumentException('Você não pode retirar seu próprio acesso administrativo.');
        if((!$id||$senha!=='')&&(strlen($senha)<8||strlen($senha)>72))throw new InvalidArgumentException('A senha deve ter entre 8 e 72 caracteres.');
        $db->beginTransaction();
        $s=$db->prepare('SELECT id FROM users WHERE username=? AND id<>?');$s->execute([$nome,$id]);if($s->fetchColumn())throw new InvalidArgumentException('Já existe um usuário com esse login.');
        if($id) {
            $s=$db->prepare('SELECT id FROM users WHERE id=?');$s->execute([$id]);if(!$s->fetch())throw new InvalidArgumentException('Usuário não encontrado.');
            $db->prepare('UPDATE users SET username=? WHERE id=?')->execute([$nome,$id]);
            if($senha!=='')$db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($senha,PASSWORD_BCRYPT,['cost'=>12]),$id]);
        } else {
            $db->prepare('INSERT INTO users(username,password_hash) VALUES(?,?)')->execute([$nome,password_hash($senha,PASSWORD_BCRYPT,['cost'=>12])]);$id=(int)$db->lastInsertId();
        }
        $db->prepare('INSERT INTO user_perfis(user_id,perfil,ativo) VALUES(?,?,?) ON DUPLICATE KEY UPDATE perfil=VALUES(perfil),ativo=VALUES(ativo)')->execute([$id,$perfil,$ativo]);
        prodAudit($db,'salvar_usuario',null,null,null,['user_id'=>$id,'login'=>$nome,'perfil'=>$perfil,'ativo'=>$ativo,'senha_alterada'=>$senha!==''],false);
        $db->commit();redirect('/admin/usuarios.php?saved=1');
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();$error=$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível salvar o usuário.';error_log('[Axion usuários] '.$e->getMessage());}
}
$users=$ready?$db->query("SELECT u.id,u.username,COALESCE(p.perfil,'admin') AS perfil,COALESCE(p.ativo,1) AS ativo FROM users u LEFT JOIN user_perfis p ON p.user_id=u.id ORDER BY u.username")->fetchAll():[];
$edit=null;foreach($users as $u)if((int)$u['id']===(int)($_GET['edit']??0))$edit=$u;
layout_start('Usuários e acessos','usuarios');
?>
<?php if(!$ready): ?><div class="card">Ative a estrutura na aba <a href="/admin/producao.php">Produção / PCP</a> para gerenciar usuários.</div><?php else: ?>
<?php if($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif ?>
<?php if(isset($_GET['saved'])): ?><div class="alert alert-success">Usuário salvo. As permissões são verificadas em cada acesso.</div><?php endif ?>
<div class="card"><div class="card-title"><?= $edit?'Editar usuário':'Novo usuário' ?></div><form method="POST" class="frow">
<?= csrfField() ?><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<div class="fg"><label>Login</label><input name="username" required maxlength="50" value="<?= e($edit['username']??'') ?>"></div>
<div class="fg"><label>Perfil</label><select name="perfil"><?php foreach(['admin'=>'Administrador','pcp'=>'PCP','ajudante'=>'Ajudante'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($edit['perfil']??'ajudante')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></div>
<div class="fg"><label><?= $edit?'Nova senha (vazia mantém a atual)':'Senha inicial' ?></label><input type="password" name="senha" autocomplete="new-password" minlength="8" maxlength="72" <?= $edit?'':'required' ?>></div>
<div class="fg"><label><input type="checkbox" name="ativo" <?= (!$edit||$edit['ativo'])?'checked':'' ?>> Usuário ativo</label></div>
<div><button class="btn btn-primary" type="submit">Salvar usuário</button> <a class="btn btn-ghost" href="/admin/usuarios.php">Cancelar</a></div></form></div>
<div class="card" id="usuarios"><p class="text-muted">PCP e ajudante não têm acesso ao financeiro, propostas, pedidos ou demais áreas atuais. Contas são desativadas para preservar a auditoria.</p><table><thead><tr><th>Login</th><th>Perfil</th><th>Situação</th><th></th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?= e($u['username']) ?></td><td><?= e($u['perfil']) ?></td><td><?= $u['ativo']?'Ativo':'Inativo' ?></td><td><a class="btn btn-secondary btn-sm" href="?edit=<?= (int)$u['id'] ?>">Editar</a></td></tr><?php endforeach ?></tbody></table></div>
<?php endif;layout_end(); ?>
