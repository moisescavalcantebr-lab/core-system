<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

$kind = (string)($_GET['kind'] ?? '');
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('error', 'Item invalido.');
    redirect('/web/admin/pages/taxonomy.php');
}

try {
    if ($kind === 'category') {
        $stmt = $pdo->prepare('SELECT name FROM blog_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $name = trim((string)$stmt->fetchColumn());

        if ($name === '') {
            flash('error', 'Categoria nao encontrada.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        $count = $pdo->prepare("
            SELECT COUNT(*)
            FROM core_page_contents
            WHERE type = 'blog'
              AND CONVERT(category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ");
        $count->execute([$name]);

        if ((int)$count->fetchColumn() > 0) {
            flash('error', 'Nao e possivel excluir categoria com artigos vinculados.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        $pdo->prepare('DELETE FROM blog_subcategories WHERE category_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM blog_categories WHERE id = ?')->execute([$id]);
        flash('success', 'Categoria excluida.');
    } elseif ($kind === 'subcategory') {
        $stmt = $pdo->prepare("
            SELECT s.name, c.name AS category_name
            FROM blog_subcategories s
            INNER JOIN blog_categories c ON c.id = s.category_id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$item) {
            flash('error', 'Subcategoria nao encontrada.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        $count = $pdo->prepare("
            SELECT COUNT(*)
            FROM core_page_contents
            WHERE type = 'blog'
              AND CONVERT(category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
              AND CONVERT(sub_category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
        ");
        $count->execute([(string)$item['category_name'], (string)$item['name']]);

        if ((int)$count->fetchColumn() > 0) {
            flash('error', 'Nao e possivel excluir subcategoria com artigos vinculados.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        $pdo->prepare('DELETE FROM blog_subcategories WHERE id = ?')->execute([$id]);
        flash('success', 'Subcategoria excluida.');
    } else {
        flash('error', 'Tipo de taxonomia invalido.');
    }
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel excluir: ' . $e->getMessage());
}

redirect('/web/admin/pages/taxonomy.php');
