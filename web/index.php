<?php

require __DIR__ . '/../app/bootstrap/bootstrap.php';
require __DIR__ . '/../app/core/Router.php';

$router = new Router();

require __DIR__ . '/../app/routes/web.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);