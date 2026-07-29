<?php
/**
 * API: recebe o formulário de orçamento do site
 * OWASP A03: prepared statements | A04: validação + rate limit | A05: headers JSON
 */
require_once dirname(__DIR__) . '/admin/_config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'msg' => 'Método não permitido.']));
}

// ── Anti-bot: honeypot + tempo mínimo de preenchimento — OWASP A04 ─
// Campo invisível: só bots costumam preencher. Falha "silenciosa"
// (responde sucesso mas não grava nada) para não ensinar o bot a
// evitar a checagem.
$honeypot = trim($_POST['website'] ?? '');
$formTs   = (int)($_POST['form_ts'] ?? 0);
$elapsed  = $formTs > 0 ? (microtime(true) * 1000 - $formTs) : 0;
if ($honeypot !== '' || $formTs <= 0 || $elapsed < 1200) {
    echo json_encode(['ok' => true, 'msg' => 'Mensagem enviada com sucesso!']);
    exit;
}

// ── Rate limit por IP (5 envios por hora) ────────────────────────
$ip   = ipHash();
$cut  = date('Y-m-d H:i:s', time() - 3600);
$cnt  = db()->prepare("SELECT COUNT(*) FROM quotes WHERE ip_hash = ? AND created_at > ?");
$cnt->execute([$ip, $cut]);
if ((int)$cnt->fetchColumn() >= 5) {
    http_response_code(429);
    exit(json_encode(['ok' => false, 'msg' => 'Muitas requisições. Tente novamente em 1 hora.']));
}

// ── Validação de entrada — OWASP A03 ────────────────────────────
$name  = trim(strip_tags($_POST['name']  ?? ''));
$email = trim($_POST['email'] ?? '');

if (strlen($name) < 2 || strlen($name) > 255) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'Nome inválido.']));
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
    http_response_code(422);
    exit(json_encode(['ok' => false, 'msg' => 'E-mail inválido.']));
}

// ── Rate limit por e-mail (5 envios por 24h) — complementa o limite
//    por IP, protegendo contra spam que rotaciona IP mas repete o
//    mesmo e-mail alvo, e evita bloquear injustamente visitantes que
//    compartilham IP (ex.: rede móvel/CGNAT) ────────────────────────
$cutEmail = date('Y-m-d H:i:s', time() - 86400);
$cntEmail = db()->prepare("SELECT COUNT(*) FROM quotes WHERE email = ? AND created_at > ?");
$cntEmail->execute([$email, $cutEmail]);
if ((int)$cntEmail->fetchColumn() >= 5) {
    http_response_code(429);
    exit(json_encode(['ok' => false, 'msg' => 'Muitas solicitações com este e-mail. Tente novamente mais tarde.']));
}

$company = substr(strip_tags(trim($_POST['company']     ?? '')), 0, 255);
$phone   = substr(preg_replace('/[^\d\s\(\)\-\+]/', '', $_POST['phone'] ?? ''), 0, 50);
$service = substr(strip_tags(trim($_POST['service']     ?? '')), 0, 255);
$desc    = substr(strip_tags(trim($_POST['description'] ?? '')), 0, 2000);

// ── Salvar no MySQL — OWASP A03: prepared statement ─────────────
try {
    db()->prepare(
        "INSERT INTO quotes (name, company, email, phone, service, description, ip_hash)
         VALUES (?,?,?,?,?,?,?)"
    )->execute([$name, $company, $email, $phone, $service, $desc, $ip]);

    echo json_encode(['ok' => true, 'msg' => 'Mensagem enviada com sucesso!']);
} catch (PDOException $e) {
    error_log('[Axion Quote] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar. Tente novamente.']);
}
