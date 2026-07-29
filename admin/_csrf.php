<?php
/**
 * CSRF Protection — OWASP A04: Insecure Design
 * Double-submit cookie pattern via session token
 */

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Valida token CSRF — chame no início de qualquer handler POST.
 * Em caso de falha, termina com 403.
 */
function csrfVerify(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        error_log('[Axion CSRF] falha de token — IP hash: ' . ipHash());
        exit('Requisição inválida. Volte e tente novamente.');
    }
}
