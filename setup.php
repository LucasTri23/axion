<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  AXION — Setup do Banco de Dados                         ║
 * ║                                                          ║
 * ║  1. Preencha as credenciais em admin/_config.php         ║
 * ║  2. Acesse este arquivo UMA VEZ no browser               ║
 * ║  3. APAGUE ou proteja este arquivo no .htaccess          ║
 * ╚══════════════════════════════════════════════════════════╝
 */

// Proteção mínima: acesso apenas via GET com token (troque o token abaixo)
define('SETUP_TOKEN', 'axion_setup_2025_xK9mP');

if (($_GET['token'] ?? '') !== SETUP_TOKEN) {
    http_response_code(403);
    die('<h2>Acesso negado.</h2><p>Passe o token correto via ?token=SEU_TOKEN</p>');
}

require_once __DIR__ . '/admin/_config.php';

$errors  = [];
$success = [];

// ── Criar tabelas ────────────────────────────────────────────────
$tables = [

'users' => "CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email         VARCHAR(255) DEFAULT '',
    last_login    DATETIME     NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'quotes' => "CREATE TABLE IF NOT EXISTS quotes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    company     VARCHAR(255) DEFAULT '',
    email       VARCHAR(255) NOT NULL,
    phone       VARCHAR(50)  DEFAULT '',
    service     VARCHAR(255) DEFAULT '',
    description TEXT,
    status      ENUM('novo','andamento','concluido','cancelado') DEFAULT 'novo',
    ip_hash     VARCHAR(64),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status  (status),
    INDEX idx_created (created_at),
    INDEX idx_ip_hash (ip_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'site_content' => "CREATE TABLE IF NOT EXISTS site_content (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_key   VARCHAR(100) NOT NULL UNIQUE,
    content_value TEXT,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'site_images' => "CREATE TABLE IF NOT EXISTS site_images (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image_key     VARCHAR(100) NOT NULL UNIQUE,
    filename      VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    mime_type     VARCHAR(50),
    file_size     INT UNSIGNED,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'propostas' => "CREATE TABLE IF NOT EXISTS propostas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo            ENUM('grande','pequena') NOT NULL,
    numero          VARCHAR(50)  DEFAULT '',
    revisao         VARCHAR(10)  DEFAULT '00',
    data_proposta   DATE         NULL,
    cliente_nome    VARCHAR(255) NOT NULL DEFAULT '',
    cliente_contato VARCHAR(255) DEFAULT '',
    cliente_cnpj    VARCHAR(30)  DEFAULT '',
    objeto          VARCHAR(255) DEFAULT '',
    dados           LONGTEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tipo    (tipo),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'login_log' => "CREATE TABLE IF NOT EXISTS login_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_hash         VARCHAR(64)  NOT NULL,
    user_agent_hash VARCHAR(64),
    user_id         INT UNSIGNED NULL,
    success         TINYINT(1)   DEFAULT 0,
    attempted_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip       (ip_hash),
    INDEX idx_attempted (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

try {
    $pdo = db();

    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        $success[] = "✔ Tabela <code>$name</code> criada/verificada.";
    }

    // ── Usuário admin padrão ─────────────────────────────────────
    // Senha padrão: Axion@2025 — TROQUE IMEDIATAMENTE nas Configurações
    $hash = password_hash('Axion@2025', PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO users (username, password_hash, email) VALUES (?,?,?)"
    );
    $stmt->execute(['admin', $hash, 'adm@axionindustrial.com.br']);

    if ($stmt->rowCount() > 0) {
        $success[] = '✔ Usuário <strong>admin</strong> criado com senha padrão <code>Axion@2025</code>.';
    } else {
        $success[] = 'ℹ️ Usuário <strong>admin</strong> já existia — senha não alterada.';
    }

    // ── Pasta uploads ────────────────────────────────────────────
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
        $success[] = '✔ Pasta <code>uploads/</code> criada.';
    } else {
        $success[] = '✔ Pasta <code>uploads/</code> já existe.';
    }

} catch (Throwable $e) {
    $errors[] = '✘ Erro: ' . htmlspecialchars($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Axion Setup</title>
<style>
body{font-family:system-ui,sans-serif;max-width:700px;margin:40px auto;padding:20px;background:#0a0f1e;color:#fff}
h1{color:#E8751A;margin-bottom:6px}
.box{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:10px;padding:18px 22px;margin-bottom:16px;font-size:14px;line-height:1.9}
.ok{border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.06)}
.err{border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.07);color:#fca5a5}
.warn{border-color:rgba(245,158,11,.3);background:rgba(245,158,11,.06);color:#fde68a;font-size:15px;font-weight:600}
code{background:rgba(255,255,255,.1);border-radius:4px;padding:1px 6px;font-size:13px}
a{color:#3AAFE4}
</style>
</head>
<body>
<h1>⚙️ Axion — Setup do Banco de Dados</h1>

<?php if ($errors): ?>
  <div class="box err">
    <strong>❌ Erros encontrados:</strong><br>
    <?= implode('<br>', $errors) ?>
    <br><br>Verifique as credenciais em <code>admin/_config.php</code> e tente novamente.
  </div>
<?php else: ?>
  <div class="box ok">
    <?= implode('<br>', $success) ?>
  </div>
  <div class="box warn">
    ⚠️ AÇÃO OBRIGATÓRIA:<br>
    1. Acesse <a href="/admin/login.php">/admin/login.php</a> com usuário <code>admin</code> senha <code>Axion@2025</code><br>
    2. Vá em Configurações e <strong>troque a senha imediatamente</strong><br>
    3. Abra <code>.htaccess</code> na raiz e <strong>descomente a linha que bloqueia setup.php</strong>
  </div>
<?php endif ?>

</body>
</html>
