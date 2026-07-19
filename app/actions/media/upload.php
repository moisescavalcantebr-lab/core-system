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

if (!isset($_FILES['images']) && !isset($_FILES['image'])) {
    exit('Nenhum arquivo enviado');
}

/* =========================
EXECUÇÃO
========================= */

$service = new MediaService($pdo);

try {

    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $total = count($_FILES['images']['name']);

        for ($i = 0; $i < $total; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $service->upload([
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i],
            ]);
        }
    } else {
        $service->upload($_FILES['image']);
    }

    /* redirect padrão */
    header('Location: /web/admin/media/');
    exit;

} catch (Throwable $e) {

    /* retorno simples (pode melhorar depois) */
    echo $e->getMessage();
}
