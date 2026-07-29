<?php
/**
 * Axion Admin — Configuração central
 * OWASP A02: Cryptographic Failures  |  A05: Security Misconfiguration
 */

/**
 * Inicia a sessão PHP sob demanda — chamar apenas nas páginas que
 * realmente precisam de sessão (login, logout, área autenticada do
 * admin). NÃO chamar a partir de páginas/APIs públicas, para não
 * emitir o cookie PHPSESSID a visitantes comuns do site.
 */
function ensureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // OWASP A07: cookies de sessão seguros
        ini_set('session.cookie_httponly',  '1');
        ini_set('session.cookie_secure',    '1');   // requer HTTPS
        ini_set('session.cookie_samesite',  'Strict');
        ini_set('session.use_strict_mode',  '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime',   '7200');
        session_start();
    }
}

// ── Credenciais e segredos — OWASP A02: nunca hardcoded no código ─
// Carregados de admin/_secrets.php (fora do Git, ver .gitignore).
// Veja admin/_secrets.example.php para o modelo esperado.
$secretsFile = __DIR__ . '/_secrets.php';
if (!is_file($secretsFile)) {
    http_response_code(500);
    exit('Configuração ausente: crie admin/_secrets.php a partir de admin/_secrets.example.php.');
}
require_once $secretsFile;
// ────────────────────────────────────────────────────────────────

define('UPLOAD_DIR',    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
define('UPLOAD_URL',    '/uploads/');
define('MAX_FILE_BYTES', 3 * 1024 * 1024);   // 3 MB
define('ALLOWED_MIME',   ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('ALLOWED_EXT',    ['jpg', 'jpeg', 'png', 'webp', 'gif']);

define('SESSION_TTL',  7200);   // 2 horas (segundos)
define('MAX_FAILS',    5);      // tentativas antes de bloquear
define('LOCKOUT_SEC',  900);    // 15 minutos de bloqueio

// ── PDO singleton ────────────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // OWASP A09: log sem expor detalhes ao usuário
            error_log('[Axion DB] ' . $e->getMessage());
            http_response_code(503);
            exit('Serviço temporariamente indisponível. Tente novamente.');
        }
    }
    return $pdo;
}

// ── Helpers ──────────────────────────────────────────────────────

/** Escapa saída para HTML — OWASP A03/XSS */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redireciona e termina */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Hasha IP para logs sem guardar dado pessoal (LGPD) */
function ipHash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return hash('sha256', $ip . IP_HASH_SALT);
}
