<?php
/**
 * Guard de autenticação — OWASP A01: Broken Access Control
 * Inclua como PRIMEIRA linha de cada página do admin.
 */
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/_permissoes.php';

ensureSession();

function requireAuth(): void {
    $uid = $_SESSION['user_id'] ?? null;
    $lat = $_SESSION['last_activity'] ?? 0;

    // Sessão ausente ou expirada
    if (!$uid || (time() - $lat) > SESSION_TTL) {
        session_unset();
        session_destroy();
        redirect('/admin/login.php?timeout=1');
    }

    // Confirma que usuário ainda existe e está ativo no BD
    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $authenticatedUser = $stmt->fetch();
    if (!$authenticatedUser) {
        session_unset();
        session_destroy();
        redirect('/admin/login.php');
    }
    $_SESSION['username'] = $authenticatedUser['username'];

    $access = perfilUsuario(db(), (int)$uid);
    if (!(int)$access['ativo']) {
        session_unset(); session_destroy();
        redirect('/admin/login.php');
    }
    $_SESSION['perfil'] = $access['perfil'];
    if (!paginaPermitida($access['perfil'], basename($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
        http_response_code(403);
        exit('Acesso restrito ao administrador. Acesse /admin/producao.php para continuar.');
    }

    // Renova timestamp de atividade
    $_SESSION['last_activity'] = time();
}

requireAuth();
