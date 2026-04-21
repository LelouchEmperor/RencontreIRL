<?php
/**
 * CSRF Protection
 * Utilisation :
 *   - Dans un formulaire : <?= csrf_field() ?>
 *   - En début de traitement POST : csrf_verify();
 */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '" />';
}

function csrf_verify(): void {
    $token_post    = $_POST['csrf_token'] ?? '';
    $token_session = $_SESSION['csrf_token'] ?? '';

    if (!$token_session || !$token_post || !hash_equals($token_session, $token_post)) {
        http_response_code(403);
        die('Action non autorisée. <a href="javascript:history.back()">Retour</a>');
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}