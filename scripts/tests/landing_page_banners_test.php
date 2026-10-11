<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
require $root.'/modules/landing_page/web/admin/landing_page/helpers.php';
function expect(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function copyTree(string $from, string $to): void {
    if (!is_dir($to)) mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $file) {
        if ($file->isDot()) continue;
        $target = $to.'/'.$file->getFilename();
        $file->isDir() ? copyTree($file->getPathname(), $target) : copy($file->getPathname(), $target);
    }
}
function runRoute(string $fixture, string $route, array $post = [], array $get = []): string {
    $code = '$_POST='.var_export($post, true).';$_GET='.var_export($get, true).';require '.var_export($fixture.'/web/'.$route, true).';';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Processo indisponivel');
    $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    expect(proc_close($process) === 0 && $error === '', 'Rota falhou: '.$error);
    return $output;
}
$config = require $root.'/env/env.production.php'; $db = $config['db'];
$pdo = new PDO('mysql:host='.$db['host'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'test_landing_banners_'.bin2hex(random_bytes(6));
$fixture = sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $schema = (string)file_get_contents($root.'/modules/landing_page/database/schema.sql');
    // Simulate an existing project, then run the additive schema upgrade twice.
    $pdo->exec(str_replace("    banners_json LONGTEXT NULL,\n", '', str_replace("\r\n", "\n", $schema)));
    $pdo->exec("INSERT INTO landing_page_pages (title,slug,headline,offer_image_2) VALUES ('Legada','legada','Titulo','storage/uploads/legacy.png')");
    define('PROJECT_PATH', $root.'/bases/marketing');
    landingPageEnsureSchema($pdo); landingPageEnsureSchema($pdo);
    $legacy = $pdo->query('SELECT * FROM landing_page_pages WHERE id=1')->fetch(PDO::FETCH_ASSOC);
    expect(landingPageBanners($legacy)[0]['image'] === 'storage/uploads/legacy.png', 'Imagem legada perdida');
    expect(landingPageBanners($legacy, true) === [], 'Imagem sem link visivel');
    $saved = landingPageSaveBanners($legacy, ['banners' => [['url' => 'https://example.com/destino', 'title' => 'Destino']]], []);
    expect(landingPageBanners(['banners_json' => $saved], true)[0]['image'] === 'storage/uploads/legacy.png', 'Edicao perdeu imagem');
    $removed = landingPageSaveBanners(['banners_json' => $saved], ['banners' => [['url' => 'https://example.com/destino', 'remove_image' => 1]]], []);
    expect(landingPageBanners(['banners_json' => $removed], true)[0]['image'] === '', 'Remocao falhou');
    foreach (['javascript:alert(1)', 'https://', '/relativo', 'data:text/html,x', ''] as $url) {
        expect(landingPageBannerUrl($url) === '', 'Link inseguro aceito');
    }
    $items = array_fill(0, 6, ['url' => 'https://example.com/', 'title' => 'Teste']);
    expect(count(landingPageBanners(['banners_json' => json_encode($items)], true)) === 4, 'Limite de banners falhou');

    copyTree($root.'/modules/landing_page', $fixture.'/modules/landing_page');
    copyTree($root.'/modules/landing_page/web', $fixture.'/web');
    mkdir($fixture.'/app/bootstrap', 0700, true); mkdir($fixture.'/app/views', 0700, true);
    $dsn = 'mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4';
    $bootstrap = '<?php define("PROJECT_PATH",'.var_export($fixture, true).'); define("APP_PATH", PROJECT_PATH."/app"); define("PUBLIC_PATH", PROJECT_PATH."/web"); define("PROJECT_URL", "/test"); $pdo=new PDO('.var_export($dsn, true).','.var_export($db['user'], true).','.var_export($db['pass'], true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); function requireProjectRole($roles){} function projectUserRole(){return "ADMIN";} function csrf_verify(){} function csrf_field(){return "<input type=hidden name=csrf value=test>";} function flash(...$args){} function flash_show(){} function redirect($url){exit;}';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php', $bootstrap);
    file_put_contents($fixture.'/app/views/layout_admin.php', '<?php echo $content;');
    $post = ['title' => 'Pagina teste', 'slug' => 'pagina-teste', 'headline' => 'Oferta principal', 'subtitle' => 'Conheca a oferta', 'body' => 'Conteudo principal para testes.', 'status' => 'published', 'banners' => [
        ['url' => 'https://example.com/destino-a', 'title' => 'Outra pagina'],
        ['url' => 'https://example.com/destino-b', 'title' => 'Mais informacoes'],
        ['url' => '', 'title' => 'Nao exibir'],
        ['url' => 'javascript:alert(1)', 'title' => 'Invalido'],
    ]];
    runRoute($fixture, 'admin/landing_page/store.php', $post);
    $page = $pdo->query("SELECT * FROM landing_page_pages WHERE slug='pagina-teste'")->fetch(PDO::FETCH_ASSOC);
    expect(is_array($page), 'Criacao falhou'); $id = (int)$page['id'];
    expect(count(landingPageBanners($page, true)) === 2, 'Criacao exibiu banners invalidos');
    $imageJson = json_decode($page['banners_json'], true); $imageJson[0]['image'] = 'storage/uploads/test.png';
    $stmt = $pdo->prepare('UPDATE landing_page_pages SET offer_image=?,banners_json=? WHERE id=?');
    $stmt->execute(['storage/uploads/test.png', json_encode($imageJson), $id]);
    $post['title'] = 'Pagina editada';
    runRoute($fixture, 'admin/landing_page/update.php', $post, ['id' => $id]);
    $page = $pdo->query('SELECT * FROM landing_page_pages WHERE id='.$id)->fetch(PDO::FETCH_ASSOC);
    expect($page['title'] === 'Pagina editada' && landingPageBanners($page)[0]['image'] === 'storage/uploads/test.png', 'Edicao perdeu dados');
    $html = runRoute($fixture, 'landing_page.php', [], ['slug' => 'pagina-teste']);
    expect(substr_count($html, 'class="banner"') === 2 && !str_contains($html, 'javascript:'), 'Renderizacao incorreta');
    expect(str_contains($html, 'href="https://example.com/destino-a"'), 'Destino alterado');
    expect($pdo->query('SELECT COUNT(*) FROM landing_page_leads')->fetchColumn() == 0, 'Banner criou lead');
    $publicDir = $root.'/_deploy'; if (!is_dir($publicDir)) mkdir($publicDir, 0700);
    file_put_contents($publicDir.'/banners-public-preview.html', $html);
    file_put_contents($publicDir.'/banners-form-preview.html', runRoute($fixture, 'admin/landing_page/edit.php', [], ['id' => $id]));
    file_put_contents($publicDir.'/banners-index-preview.html', runRoute($fixture, 'admin/landing_page/index.php'));
    $post['banners'] = [];
    runRoute($fixture, 'admin/landing_page/update.php', $post, ['id' => $id]);
    $html = runRoute($fixture, 'landing_page.php', [], ['slug' => 'pagina-teste']);
    expect(!str_contains($html, '<section class="banners"'), 'Secao vazia visivel');
    echo "OK: schema legado, preservacao, URLs, limite, criacao, edicao, banners opcionais, destinos e leads isolados.\n";
} finally {
    $pdo->exec('DROP DATABASE `'.$database.'`');
    if (is_dir($fixture) && str_starts_with($fixture, sys_get_temp_dir().'/test_landing_banners_')) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($fixture);
    }
}
