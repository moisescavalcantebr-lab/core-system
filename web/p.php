<?php

require __DIR__ . '/../app/bootstrap/bootstrap.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

/* =========================
SLUG
========================= */

$slug = $_GET['slug'] ?? '';

if(!$slug){
    die('Slug não informado.');
}

/* =========================
PREVIEW
========================= */

$preview = isset($_GET['preview']);

if($preview){

    $stmt = $pdo->prepare("
        SELECT *
        FROM core_page_contents
        WHERE slug = :slug
        AND area = 'public'
        LIMIT 1
    ");

}else{

    $stmt = $pdo->prepare("
        SELECT *
        FROM core_page_contents
        WHERE slug = :slug
        AND status = 'published'
        AND area = 'public'
        LIMIT 1
    ");

}

$stmt->execute(['slug'=>$slug]);

$page = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$page){
    die('Página não encontrada.');
}

/* =========================
TIPO DE LAYOUT
========================= */

$layoutType = $page['type'] ?? 'page';


/*
|--------------------------------------------------------------------------
| JSON da página
|--------------------------------------------------------------------------
*/

$jsonPath = STORAGE_PATH.'/paginas/pages/'.$page['content_path'];

$data = ['blocks' => []]; // ← GARANTE SEMPRE

if(file_exists($jsonPath)){
    $decoded = json_decode(file_get_contents($jsonPath), true);

    if(is_array($decoded)){
        $data = $decoded;
    }
}

$blocks = $data['blocks'] ?? [];
/*
|--------------------------------------------------------------------------
| RENDER
|--------------------------------------------------------------------------
*/

$renderService = new PageRenderService();

ob_start();

$renderService->render($blocks, $data ?? []);

$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

$title = $page['title'] ?? 'Página';

$previewMode = $preview;

/*
|--------------------------------------------------------------------------
| LAYOUT
|--------------------------------------------------------------------------
*/

switch ($layoutType) {

    case 'blog':
        require APP_PATH . '/views/layout_blog.php';
        break;

    case 'page':
    default:
        require APP_PATH . '/views/layout_page.php';
        break;
}
