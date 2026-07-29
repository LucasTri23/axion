<?php
/**
 * Login — OWASP A07: Identification and Authentication Failures
 * - Rate limiting por IP (hash)
 * - bcrypt com cost 12
 * - Mensagem genérica (sem info disclosure)
 * - Regenera session ID após login
 */
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_csrf.php';

ensureSession();

// Já autenticado → dashboard
if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) < SESSION_TTL) {
    redirect('/admin/index.php');
}

$error   = '';
$timeout = isset($_GET['timeout']);

// ── Rate limiting ────────────────────────────────────────────────
function countRecentFails(): int {
    $cut = date('Y-m-d H:i:s', time() - LOCKOUT_SEC);
    $s = db()->prepare(
        "SELECT COUNT(*) FROM login_log WHERE ip_hash = ? AND success = 0 AND attempted_at > ?"
    );
    $s->execute([ipHash(), $cut]);
    return (int)$s->fetchColumn();
}

function logAttempt(bool $success, ?int $userId = null): void {
    db()->prepare(
        "INSERT INTO login_log (ip_hash, user_agent_hash, success, user_id) VALUES (?,?,?,?)"
    )->execute([
        ipHash(),
        hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''),
        $success ? 1 : 0,
        $userId
    ]);
}

// ── Processar POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $fails = countRecentFails();
    if ($fails >= MAX_FAILS) {
        $mins  = ceil(LOCKOUT_SEC / 60);
        $error = "Acesso bloqueado por $mins minutos devido a tentativas incorretas.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Busca usuário — OWASP A03: prepared statement
        $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Valida senha com timing-safe compare (password_verify já é timing-safe)
        if ($user && password_verify($password, $user['password_hash'])) {
            logAttempt(true, (int)$user['id']);

            // OWASP A07: regenerar ID de sessão após login
            session_regenerate_id(true);
            $_SESSION['user_id']       = (int)$user['id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['last_activity'] = time();

            // Atualiza last_login no BD
            db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
               ->execute([$user['id']]);

            redirect('/admin/index.php');
        } else {
            // Mesmo que usuário não exista — mensagem genérica (A07: no info disclosure)
            logAttempt(false);
            $remaining = max(0, MAX_FAILS - $fails - 1);
            $error = 'Usuário ou senha incorretos.';
            if ($remaining <= 2 && $remaining > 0) {
                $error .= " ($remaining tentativa(s) restante(s) antes do bloqueio)";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Axion — Acesso Restrito</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#04090F 0%,#080F1E 60%,#0D1B3E 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;color:#fff}
.card{background:rgba(255,255,255,.04);border:1px solid rgba(58,175,228,.18);border-radius:22px;padding:48px 42px;width:100%;max-width:400px;text-align:center;box-shadow:0 32px 80px rgba(0,0,0,.6)}
.card img{height:48px;margin-bottom:24px;filter:drop-shadow(0 4px 16px rgba(232,117,26,.3))}
.card h2{font-family:'Bebas Neue',sans-serif;font-size:26px;letter-spacing:2px;margin-bottom:4px}
.card>p{font-size:12.5px;color:#7B8899;margin-bottom:28px}
.shield{font-size:12.5px;display:flex;align-items:center;justify-content:center;gap:6px;background:rgba(232,117,26,.08);border:1px solid rgba(232,117,26,.22);border-radius:8px;padding:8px;margin-bottom:26px;color:rgba(255,255,255,.6)}
.fg{display:flex;flex-direction:column;gap:5px;margin-bottom:14px;text-align:left}
.fg label{font-size:11.5px;font-weight:600;color:#B8C4D0;letter-spacing:.4px;text-transform:uppercase}
.fg input{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.12);border-radius:9px;padding:12px 15px;color:#fff;font-size:14.5px;font-family:'Inter',sans-serif;outline:none;transition:.25s;width:100%}
.fg input:focus{border-color:#3AAFE4;background:rgba(58,175,228,.05)}
.btn{width:100%;padding:14px;background:linear-gradient(135deg,#E8751A,#FF8F2C);color:#fff;border:none;border-radius:9px;font-size:14.5px;font-weight:700;cursor:pointer;font-family:'Inter',sans-serif;transition:.3s;margin-top:4px}
.btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 8px 28px rgba(232,117,26,.45)}
.btn:disabled{opacity:.6;cursor:not-allowed}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);border-radius:8px;padding:10px 14px;font-size:13px;color:#fca5a5;margin-top:14px;text-align:left}
.alert-info{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.22);border-radius:8px;padding:10px 14px;font-size:13px;color:#fde68a;margin-top:14px}
</style>
</head>
<body>
<div class="card">
  <img src="/img/logo.png" alt="Axion" onerror="this.style.display='none'">
  <h2>Painel Administrativo</h2>
  <p>Acesso restrito — somente pessoal autorizado</p>
  <div class="shield">🔒 Conexão protegida · Sessão criptografada</div>

  <?php if ($timeout): ?>
    <div class="alert-info">⏱ Sessão expirada por inatividade. Faça login novamente.</div>
  <?php endif ?>

  <form method="POST" action="" autocomplete="off">
    <?= csrfField() ?>
    <div class="fg">
      <label>Usuário</label>
      <input type="text" name="username" placeholder="admin" required autofocus
             autocomplete="username" maxlength="50"
             value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="fg">
      <label>Senha</label>
      <input type="password" name="password" placeholder="••••••••••" required
             autocomplete="current-password" maxlength="128">
    </div>
    <button type="submit" class="btn">Entrar no Painel</button>
    <?php if ($error): ?>
      <div class="alert-error">⚠️ <?= e($error) ?></div>
    <?php endif ?>
  </form>
</div>
</body>
</html>
