<?php
declare(strict_types=1);

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
    if (
        !isset($_POST['_csrf']) ||
        !hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'])
    ) {
        http_response_code(419);
        die('CSRF token inválido.');
    }
}
