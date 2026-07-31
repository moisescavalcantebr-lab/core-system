<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
require APP_PATH . '/helpers/flash.php';

requireAdmin();

function blogTaxonomySlug(string $name): string
{
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(trim($name))) ?? '';
    return trim($slug, '-');
}

$kind = (string)($_POST['kind'] ?? '');
$id = (int)($_POST['id'] ?? 0);
$name = preg_replace('/\s+/', ' ', trim((string)($_POST['name'] ?? ''))) ?? '';
$name = trim($name);
$categoryId = (int)($_POST['category_id'] ?? 0);

if ($name === '' || strlen($name) > 100) {
    flash('error', 'Informe um nome com ate 100 caracteres.');
    redirect('/web/admin/pages/taxonomy.php');
}

$slug = blogTaxonomySlug($name);
if ($slug === '') {
    flash('error', 'Nome invalido para gerar slug.');
    redirect('/web/admin/pages/taxonomy.php');
}

try {
    if ($kind === 'category') {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT name FROM blog_categories WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $oldName = trim((string)$stmt->fetchColumn());

            if ($oldName === '') {
                flash('error', 'Categoria nao encontrada.');
                redirect('/web/admin/pages/taxonomy.php');
            }

            $pdo->prepare('UPDATE blog_categories SET name = ?, slug = ? WHERE id = ?')->execute([$name, $slug, $id]);
            $pdo->prepare("
                UPDATE core_page_contents
                SET category = ?, updated_at = NOW()
                WHERE type = 'blog'
                  AND CONVERT(category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ")->execute([$name, $oldName]);
            flash('success', 'Categoria atualizada.');
        } else {
            $pdo->prepare('INSERT INTO blog_categories (name, slug, status) VALUES (?, ?, 1)')->execute([$name, $slug]);
            flash('success', 'Categoria criada.');
        }
    } elseif ($kind === 'subcategory') {
        if ($categoryId <= 0) {
            flash('error', 'Selecione a categoria da subcategoria.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        $stmt = $pdo->prepare('SELECT name FROM blog_categories WHERE id = ? LIMIT 1');
        $stmt->execute([$categoryId]);
        $categoryName = trim((string)$stmt->fetchColumn());

        if ($categoryName === '') {
            flash('error', 'Categoria da subcategoria nao encontrada.');
            redirect('/web/admin/pages/taxonomy.php');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT s.name, c.name AS category_name
                FROM blog_subcategories s
                INNER JOIN blog_categories c ON c.id = s.category_id
                WHERE s.id = ?
                LIMIT 1
            ");
            $stmt->execute([$id]);
            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                flash('error', 'Subcategoria nao encontrada.');
                redirect('/web/admin/pages/taxonomy.php');
            }

            $pdo->prepare('UPDATE blog_subcategories SET category_id = ?, name = ?, slug = ? WHERE id = ?')->execute([$categoryId, $name, $slug, $id]);
            $pdo->prepare("
                UPDATE core_page_contents
                SET category = ?, sub_category = ?, updated_at = NOW()
                WHERE type = 'blog'
                  AND CONVERT(category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
                  AND CONVERT(sub_category USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci
            ")->execute([$categoryName, $name, (string)$old['category_name'], (string)$old['name']]);
            flash('success', 'Subcategoria atualizada.');
        } else {
            $pdo->prepare('INSERT INTO blog_subcategories (category_id, name, slug, status) VALUES (?, ?, ?, 1)')->execute([$categoryId, $name, $slug]);
            flash('success', 'Subcategoria criada.');
        }
    } else {
        flash('error', 'Tipo de taxonomia invalido.');
    }
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel salvar: ' . $e->getMessage());
}

redirect('/web/admin/pages/taxonomy.php');
