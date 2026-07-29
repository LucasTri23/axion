<?php
/**
 * Logout seguro — OWASP A07
 * Destrói sessão completamente, invalida cookie
 */
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/_csrf.php';

ensureSession();

// Só aceita POST com CSRF para evitar logout por CSRF (logout CSRF attack)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
}

$_SESSION = [];

// Invalida o cookie de sessão
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']
    );
}

session_destroy();
redirect('/admin/login.php');
