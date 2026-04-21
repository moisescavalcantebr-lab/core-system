<?php
declare(strict_types=1);

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('flash_show')) {
    function flash_show(): void
    {
        if (!empty($_SESSION['_flash'])) {

            $flash = $_SESSION['_flash'];
            unset($_SESSION['_flash']);

            echo '<div class="c-card" style="border-left:5px solid ' .
                 ($flash['type'] === 'error' ? 'red' : 'green') .
                 ';">' .
                 htmlspecialchars($flash['message']) .
                 '</div>';
        }
    }
}