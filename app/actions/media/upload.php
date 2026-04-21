<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';require APP_PATH . '/helpers/auth.php';

/* =========================
VALIDAÇÃO BÁSICA
========================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método inválido');
}

if (!isset($_FILES['image'])) {
    exit('Nenhum arquivo enviado');
}

/* =========================
EXECUÇÃO
========================= */

$service = new MediaService($pdo);

try {

    $service->upload($_FILES['image']);

    /* redirect padrão */
    header('Location: /public/admin/media/');
    exit;

} catch (Throwable $e) {

    /* retorno simples (pode melhorar depois) */
    echo $e->getMessage();
}