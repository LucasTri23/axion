<?php
/**
 * Configurações — OWASP A07: Password management
 * - Valida senha atual antes de trocar
 * - bcrypt cost 12
 * - Invalida sessão após troca de senha (requer novo login)
 */
require_once __DIR__ . '/_auth.php';
require_once __DIR__ . '/_layout.php';

$db      = db();
$msgPass = '';
$clsPass = 'alert-success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    // ── Alterar senha ──────────────────────────────────────────
    if ($action === 'change_password') {
        $oldPass  = $_POST['old_password']  ?? '';
        $newPass  = $_POST['new_password']  ?? '';
        $confPass = $_POST['conf_password'] ?? '';

        $user = $db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $user->execute([$_SESSION['user_id']]);
        $hash = $user->fetchColumn();

        if (!password_verify($oldPass, $hash)) {
            $msgPass = '⚠️ Senha atual incorreta.';
            $clsPass = 'alert-error';
        } elseif (strlen($newPass) < 8) {
            $msgPass = '⚠️ A nova senha deve ter pelo menos 8 caracteres.';
            $clsPass = 'alert-error';
        } elseif ($newPass !== $confPass) {
            $msgPass = '⚠️ As senhas não coincidem.';
            $clsPass = 'alert-error';
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
               ->execute([$newHash, $_SESSION['user_id']]);

            // OWASP A07: força novo login após troca de senha
            session_unset();
            session_destroy();
            redirect('/admin/login.php?msg=pass_changed');
        }
    }
}

layout_start('Configurações', 'settings');
?>

<div style="max-width:520px">

  <div class="card">
    <div class="card-title">🔑 Alterar Senha de Acesso</div>
    <?php if ($msgPass): ?>
      <div class="alert <?= $clsPass ?>"><?= e($msgPass) ?></div>
    <?php endif ?>
    <form method="POST" autocomplete="off">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="change_password">
      <div class="fg">
        <label>Senha Atual</label>
        <input type="password" name="old_password" required autocomplete="current-password" maxlength="128">
      </div>
      <div class="fg">
        <label>Nova Senha</label>
        <input type="password" name="new_password" required autocomplete="new-password"
               maxlength="128" minlength="8" id="np">
        <small style="color:var(--gray);font-size:12px">Mínimo 8 caracteres. Use letras, números e símbolos.</small>
      </div>
      <div class="fg">
        <label>Confirmar Nova Senha</label>
        <input type="password" name="conf_password" required autocomplete="new-password" maxlength="128">
      </div>
      <button type="submit" class="btn btn-primary">🔑 Alterar Senha</button>
      <p style="font-size:12px;color:var(--gray);margin-top:12px">
        ⚠️ Após alterar a senha, você será redirecionado para o login.
      </p>
    </form>
  </div>

  <div class="card">
    <div class="card-title">ℹ️ Informações do Sistema</div>
    <dl class="detail-grid" style="font-size:13px">
      <dt>Usuário logado</dt>     <dd><?= e($_SESSION['username'] ?? '') ?></dd>
      <dt>PHP</dt>                <dd><?= e(PHP_VERSION) ?></dd>
      <dt>Servidor</dt>           <dd>Locaweb Linux — Apache</dd>
      <dt>Sessão expira em</dt>   <dd><?= round((SESSION_TTL - (time() - $_SESSION['last_activity'])) / 60) ?> min</dd>
    </dl>
    <div style="margin-top:16px;padding:12px;background:rgba(58,175,228,.06);border:1px solid rgba(58,175,228,.15);border-radius:8px;font-size:12.5px;color:var(--gray2);line-height:1.7">
      🔒 <strong>Segurança ativa:</strong> SHA-256 + bcrypt · Sessões httpOnly + Secure · CSRF em todos os formulários · Rate limiting por IP · Headers OWASP no .htaccess
    </div>
  </div>

</div>

<?php layout_end(); ?>
