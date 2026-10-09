<?php

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!$token || !hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token inválido.');
    }
}