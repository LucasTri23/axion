<?php
/**
 * Guard de autenticação — OWASP A01: Broken Access Control
 * Inclua como PRIMEIRA linha de cada página do admin.
 */
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_csrf.php';

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
    $stmt = db()->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$uid]);
    if (!$stmt->fetch()) {
        session_unset();
        session_destroy();
        redirect('/admin/login.php');
    }

    // Renova timestamp de atividade
    $_SESSION['last_activity'] = time();
}

requireAuth();
