<?php

if (!function_exists('base_showcase_find_page')) {
    function base_showcase_find_page(PDO $pdo, array $base, bool $publishedOnly = false): ?array
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($base['slug'] ?? '')));

        if ($slug === '') {
            return null;
        }

        $statusSql = $publishedOnly ? "AND status = 'published'" : '';

        $stmt = $pdo->prepare("
            SELECT id, slug, title, status, content_path
            FROM core_page_contents
            WHERE area = 'public'
              AND type = 'page'
              {$statusSql}
              AND (
                  slug = :slug
                  OR slug LIKE :slug_like
                  OR LOWER(title) LIKE :title_like
              )
            ORDER BY
                CASE
                    WHEN slug = :slug_order THEN 0
                    WHEN slug LIKE :slug_like_order THEN 1
                    ELSE 2
                END,
                id ASC
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => $slug,
            'slug_like' => '%' . $slug . '%',
            'title_like' => '%' . str_replace('-', ' ', $slug) . '%',
            'slug_order' => $slug,
            'slug_like_order' => '%' . $slug . '%',
        ]);

        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        return $page ?: null;
    }
}

if (!function_exists('base_showcase_unique_page_slug')) {
    function base_showcase_unique_page_slug(PDO $pdo, string $slug): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($slug));
        $slug = trim((string)$slug, '-');
        $slug = $slug !== '' ? $slug : 'base';
        $baseSlug = $slug;
        $suffix = 1;

        while (true) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM core_page_contents
                WHERE slug = :slug
                  AND type IN ('page', 'blog')
            ");
            $stmt->execute(['slug' => $slug]);

            if ((int)$stmt->fetchColumn() === 0) {
                return $slug;
            }

            $slug = $baseSlug . '-' . $suffix;
            $suffix++;
        }
    }
}

if (!function_exists('base_showcase_model_data')) {
    function base_showcase_model_data(PDO $pdo): array
    {
        $paths = [
            STORAGE_PATH . '/paginas/models/model_page.json',
        ];

        $stmt = $pdo->prepare("
            SELECT content_path
            FROM core_page_contents
            WHERE slug = 'model_page'
              AND type = 'model'
            LIMIT 1
        ");
        $stmt->execute();
        $contentPath = trim((string)$stmt->fetchColumn());

        if ($contentPath !== '') {
            $paths[] = STORAGE_PATH . '/paginas/models/' . basename($contentPath);
            $paths[] = STORAGE_PATH . '/paginas/pages/' . basename($contentPath);
        }

        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                $data = json_decode((string)file_get_contents($path), true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        return ['show_title' => false, 'blocks' => []];
    }
}

if (!function_exists('base_showcase_create_page')) {
    function base_showcase_create_page(PDO $pdo, array $base): int
    {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($base['slug'] ?? '')));
        $title = trim((string)($base['showcase_title'] ?? '')) ?: trim((string)($base['name'] ?? ''));

        if ($slug === '' || $title === '') {
            throw new RuntimeException('Base sem slug ou nome para criar pagina.');
        }

        $existingPage = base_showcase_find_page($pdo, $base, false);
        if ($existingPage) {
            return (int)$existingPage['id'];
        }

        $data = base_showcase_model_data($pdo);
        $data['blocks'] = is_array($data['blocks'] ?? null) ? $data['blocks'] : [];

        foreach ($data['blocks'] as &$block) {
            if (!is_array($block)) {
                continue;
            }

            if (($block['type'] ?? '') === 'hero') {
                $block['title'] = $title;
                $block['subtitle'] = trim((string)($base['showcase_summary'] ?? '')) ?: trim((string)($base['description'] ?? ''));
                $block['cta_enabled'] = '0';
                $block['cta_text'] = 'Quero saber mais';
                $block['cta_url'] = '';
                $block['cta_target'] = '_self';
            }

            if (in_array(($block['type'] ?? ''), ['lead_form', 'form_lead_simple'], true)) {
                $block['base_id'] = (int)$base['id'];
                $block['base_slug'] = $slug;
                $block['title'] = 'Vamos criar o projeto';
                $block['description'] = 'Informe seu e-mail para receber o link de continuacao.';
                $block['button_text'] = 'Receber link';
            }
        }
        unset($block);

        $jsonDir = STORAGE_PATH . '/paginas/pages/';
        if (!is_dir($jsonDir) && !mkdir($jsonDir, 0755, true) && !is_dir($jsonDir)) {
            throw new RuntimeException('Nao foi possivel criar a pasta de paginas.');
        }

        $fileName = uniqid('page_', true) . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($jsonDir . $fileName, $json) === false) {
            throw new RuntimeException('Nao foi possivel salvar a pagina da base.');
        }

        $pageSlug = base_showcase_unique_page_slug($pdo, $slug);

        $stmt = $pdo->prepare("
            INSERT INTO core_page_contents
            (title, slug, type, model_slug, category, content_path, status, area, created_at)
            VALUES
            (:title, :slug, 'page', 'model_page', 'Produtos', :content_path, 'draft', 'public', NOW())
        ");
        $stmt->execute([
            'title' => $title,
            'slug' => $pageSlug,
            'content_path' => $fileName,
        ]);

        return (int)$pdo->lastInsertId();
    }
}
