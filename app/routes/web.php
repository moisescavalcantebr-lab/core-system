<?php

$router->get('/', function () {
    header('Location: /web/admin/login.php');
    exit;
});

$router->get('/login', function () {
    require __DIR__ . '/../../web/admin/login.php';
});