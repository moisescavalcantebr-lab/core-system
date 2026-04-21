<?php

function flash(string $type, string $message): void
{
    $_SESSION['flash'][$type][] = $message;
}

function flash_show(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    foreach ($_SESSION['flash'] as $type => $messages) {
        foreach ($messages as $msg) {

            $class = match($type) {
                'error' => 'c-alert c-alert--error',
                'success' => 'c-alert c-alert--success',
                default => 'c-alert'
            };

            echo "<div class=\"$class\">".htmlspecialchars($msg)."</div>";
        }
    }

    unset($_SESSION['flash']);
}