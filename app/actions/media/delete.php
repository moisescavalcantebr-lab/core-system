<?php
declare(strict_types=1);

require dirname(__DIR__, 3) . '/app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

/* =========================
VALIDAÇÃO
========================= */

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('ID inválido');
}

/* =========================
EXECUÇÃO
========================= */

$service = new MediaService($pdo);

try {

    $service->delete($id);

    echo 'Imagem excluída com sucesso';

} catch (Throwable $e) {

    echo 'Erro ao excluir imagem';
}