<?php
declare(strict_types=1);
ini_set('display_errors', 1);
error_reporting(E_ALL);

class PageService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================
    LISTAR
    ========================= */

    public function all(array $filters = []): array
    {
        $where = "WHERE area='public' AND type IN ('page','blog')";
        $params = [];

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where .= " AND type = :type";
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $where .= " AND category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['model']) && $filters['model'] !== 'all') {
            $where .= " AND model_slug = :model";
            $params['model'] = $filters['model'];
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where .= " AND status = :status";
            $params['status'] = $filters['status'];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, title, slug, type, model_slug, status, category
            FROM core_page_contents
            {$where}
            ORDER BY id DESC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================
    BUSCAR
    ========================= */

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM core_page_contents
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /* =========================
    TOGGLE STATUS
    ========================= */

    public function toggleStatus(int $id): string
    {
        $stmt = $this->pdo->prepare("
            SELECT status FROM core_page_contents WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        $current = $stmt->fetchColumn();

        if (!$current) {
            throw new Exception('PÃ¡gina nÃ£o encontrada');
        }

        $newStatus = ($current === 'published') ? 'draft' : 'published';

        $this->pdo->prepare("
            UPDATE core_page_contents
            SET status = :status
            WHERE id = :id
        ")->execute([
            'status' => $newStatus,
            'id' => $id
        ]);

        return $newStatus;
    }

    /* =========================
    DELETE
    ========================= */

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare("
            SELECT content_path, status 
            FROM core_page_contents 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        $page = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$page) {
            throw new Exception('PÃ¡gina nÃ£o encontrada.');
        }

        if ($page['status'] === 'published') {
            throw new Exception('Despublique a página antes de excluir.');
        }

        $file = STORAGE_PATH . '/paginas/pages/' . $page['content_path'];

        if ($file && file_exists($file)) {
            @unlink($file); // evita crash
        }

        $this->pdo->prepare("
            DELETE FROM core_page_contents
            WHERE id = :id
        ")->execute(['id' => $id]);
    }
/* =========================
CREATE FROM MODEL
========================= */

public function createFromModel(
    string $title,
    string $slug,
    string $type,
    string $modelSlug
): int {

    // slug seguro
    $slug = strtolower($slug);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');

    if (!$title || !$slug || !$modelSlug) {
        throw new Exception('Dados inválidos.');
    }

    /* =========================
    VALIDAR SLUG
    ========================= */

    $stmt = $this->pdo->prepare("
        SELECT id FROM core_page_contents WHERE slug = :slug LIMIT 1
    ");
    $stmt->execute(['slug' => $slug]);

    if ($stmt->fetch()) {
        throw new Exception('Slug já em uso.');
    }

    /* =========================
    CARREGAR MODEL
    ========================= */

    $modelPath = STORAGE_PATH . '/paginas/models/' . $modelSlug . '.json';

    if (!file_exists($modelPath)) {
        throw new Exception('Modelo não encontrado.');
    }

    $modelData = json_decode(file_get_contents($modelPath), true);

    if (!is_array($modelData)) {
        throw new Exception('Modelo inválido.');
    }

    /* =========================
    NORMALIZAR BLOCKS
    ========================= */

    $blocks = $modelData['blocks'] ?? [];

    $blocks = array_values($blocks);

    foreach ($blocks as &$block) {

        if (!isset($block['type'])) {
            continue;
        }

        $block['enabled'] = $block['enabled'] ?? true;

        // garante que não venha lixo
        foreach ($block as $k => $v) {
            if ($v === null) {
                unset($block[$k]);
            }
        }
    }

    /* =========================
    GERAR JSON FINAL
    ========================= */

    $pageJson = [
        'show_title' => $modelData['show_title'] ?? false,
        'media'      => $modelData['media'] ?? [],
        'blocks'     => $blocks
    ];

    $json = json_encode($pageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        throw new Exception('Erro ao gerar JSON.');
    }

    /* =========================
    GERAR ARQUIVO
    ========================= */

    $fileName = 'page_' . uniqid() . '.json';
    $filePath = STORAGE_PATH . '/paginas/pages/' . $fileName;

    file_put_contents($filePath, $json);

    /* =========================
    SALVAR NO BANCO
    ========================= */

    $this->pdo->prepare("
        INSERT INTO core_page_contents
        (title, slug, type, model_slug, content_path, status, area, created_at)
        VALUES
        (:title, :slug, :type, :model, :path, 'draft', 'public', NOW())
    ")->execute([
        'title' => $title,
        'slug'  => $slug,
        'type'  => $type,
        'model' => $modelSlug,
        'path'  => $fileName
    ]);

    return (int)$this->pdo->lastInsertId();
}
    /* =========================
    UPDATE
    ========================= */

    public function update(int $id, string $title, string $slug, string $model): void
    {
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (!$title || !$slug) {
            throw new Exception('Dados invalidos.');
        }

        $stmt = $this->pdo->prepare("
            SELECT id
            FROM core_page_contents
            WHERE slug = :slug
            AND id != :id
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => $slug,
            'id'   => $id
        ]);

        if ($stmt->fetch()) {
            throw new Exception('Slug ja¡ esta¡ em uso.');
        }

        $this->pdo->prepare("
            UPDATE core_page_contents
            SET
                title = :title,
                slug = :slug,
                model_slug = :model,
                updated_at = NOW()
            WHERE id = :id
        ")->execute([
            'title' => $title,
            'slug'  => $slug,
            'model' => $model,
            'id'    => $id
        ]);
    }

} 