<?php
/**
 * ╔══════════════════════════════════════════════════════════╗
 * ║  AXION — Setup do Banco de Dados                         ║
 * ║                                                          ║
 * ║  1. Preencha as credenciais em admin/_secrets.php         ║
 * ║  2. Acesse este arquivo UMA VEZ no browser               ║
 * ║  3. APAGUE ou proteja este arquivo no .htaccess          ║
 * ╚══════════════════════════════════════════════════════════╝
 */

require_once __DIR__ . '/admin/_config.php';

// Proteção mínima: acesso apenas via GET com token (SETUP_TOKEN vem de admin/_secrets.php)
if (!hash_equals(SETUP_TOKEN, $_GET['token'] ?? '')) {
    http_response_code(403);
    die('<h2>Acesso negado.</h2><p>Passe o token correto via ?token=SEU_TOKEN</p>');
}

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

// ── Módulo Financeiro / Centros de Custo ───────────────────────────

'centro_obras' => "CREATE TABLE IF NOT EXISTS centro_obras (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    os          VARCHAR(20)  DEFAULT '',
    nome        VARCHAR(255) NOT NULL,
    cliente     VARCHAR(255) DEFAULT '',
    status      ENUM('ativo','concluido') DEFAULT 'ativo',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_custos_obra' => "CREATE TABLE IF NOT EXISTS centro_custos_obra (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    obra_id     INT UNSIGNED NOT NULL,
    categoria   ENUM('material','mao_obra','terceiros','impostos') DEFAULT 'material',
    descricao   VARCHAR(255) DEFAULT '',
    valor       DECIMAL(12,2) NOT NULL DEFAULT 0,
    data        DATE NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_obra (obra_id),
    CONSTRAINT fk_custo_obra FOREIGN KEY (obra_id) REFERENCES centro_obras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_lancamentos' => "CREATE TABLE IF NOT EXISTS centro_lancamentos (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo                ENUM('ENTRADA','SAIDA') NOT NULL,
    descricao           VARCHAR(255) DEFAULT '',
    os                  VARCHAR(20)  DEFAULT '',
    nfe                 VARCHAR(50)  DEFAULT '',
    cliente_fornecedor  VARCHAR(255) DEFAULT '',
    parcela             VARCHAR(20)  DEFAULT '',
    data_lancamento     DATE NULL,
    vencimento          DATE NULL,
    valor_entrada       DECIMAL(12,2) NOT NULL DEFAULT 0,
    valor_saida         DECIMAL(12,2) NOT NULL DEFAULT 0,
    situacao            VARCHAR(20)  DEFAULT '',
    centro              VARCHAR(100) DEFAULT 'GERAL',
    observacao          VARCHAR(255) DEFAULT '',
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_data   (data_lancamento),
    INDEX idx_tipo   (tipo),
    INDEX idx_centro (centro)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_funcionarios' => "CREATE TABLE IF NOT EXISTS centro_funcionarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(150) NOT NULL UNIQUE,
    cpf         VARCHAR(20)  DEFAULT '',
    telefone    VARCHAR(30)  DEFAULT '',
    pix_chave   VARCHAR(150) DEFAULT '',
    banco_info  VARCHAR(150) DEFAULT '',
    ativo       TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_folha_periodos' => "CREATE TABLE IF NOT EXISTS centro_folha_periodos (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    data_periodo  DATE NOT NULL,
    competencia   CHAR(7) NULL,
    tipo          ENUM('Adiantamento','Fechamento') DEFAULT 'Fechamento',
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_data (data_periodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_folha_pagamentos' => "CREATE TABLE IF NOT EXISTS centro_folha_pagamentos (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    periodo_id       INT UNSIGNED NOT NULL,
    funcionario_id   INT UNSIGNED NOT NULL,
    valor            DECIMAL(12,2) NOT NULL DEFAULT 0,
    vale_transporte  DECIMAL(12,2) NOT NULL DEFAULT 0,
    observacao       VARCHAR(255) DEFAULT '',
    situacao_pagamento VARCHAR(20) NOT NULL DEFAULT 'a_confirmar',
    valor_pago DECIMAL(12,2) NULL,
    data_pagamento DATE NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_periodo (periodo_id),
    INDEX idx_func    (funcionario_id),
    CONSTRAINT fk_pgto_periodo FOREIGN KEY (periodo_id) REFERENCES centro_folha_periodos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pgto_func    FOREIGN KEY (funcionario_id) REFERENCES centro_funcionarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_import_pendencias' => "CREATE TABLE IF NOT EXISTS centro_import_pendencias (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, origem VARCHAR(255) NOT NULL, sha256 CHAR(64) NOT NULL, dados LONGTEXT NOT NULL, resolvida TINYINT NOT NULL DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

'centro_jato_pintura' => "CREATE TABLE IF NOT EXISTS centro_jato_pintura (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cliente     VARCHAR(150) NOT NULL,
    produto     VARCHAR(150) DEFAULT '',
    data_saida  DATE NULL,
    kg          DECIMAL(10,2) NOT NULL DEFAULT 0,
    vr_kg       DECIMAL(8,2)  NOT NULL DEFAULT 1.5,
    vr_total    DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_data (data_saida)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_custos_fixos' => "CREATE TABLE IF NOT EXISTS centro_custos_fixos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    descricao   VARCHAR(150) NOT NULL,
    valor       DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'centro_audit_log' => "CREATE TABLE IF NOT EXISTS centro_audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,
    username    VARCHAR(50)  DEFAULT '',
    acao        ENUM('create','update','delete') NOT NULL,
    tabela      VARCHAR(50)  NOT NULL,
    registro_id INT UNSIGNED NOT NULL,
    detalhes    TEXT,
    ip_hash     VARCHAR(64)  DEFAULT '',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabela  (tabela),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

$tables = array_merge($tables, require __DIR__ . '/admin/_producao_schema.php', require __DIR__ . '/admin/_financeiro_recorrencias_schema.php');

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
    <br><br>Verifique as credenciais em <code>admin/_secrets.php</code> e tente novamente.
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
