<?php
declare(strict_types=1);

require_once __DIR__.'/base_identity.php';

function marketingRenameIdentity(PDO $pdo, bool $apply = true): ?int
{
    $pdo->beginTransaction();
    try {
        $rows = $pdo->query("SELECT id,slug FROM bases WHERE slug IN ('divulgacao','marketing') ORDER BY id FOR UPDATE")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) {
            throw new RuntimeException('Divulgacao e Marketing coexistem. Resolva o conflito antes de migrar.');
        }
        $id = $rows ? (int)$rows[0]['id'] : null;
        if ($id !== null) {
            $prices = $pdo->prepare("SELECT id,module_slug FROM base_module_prices WHERE base_id=? AND module_slug IN ('divulgacao','landing_page') FOR UPDATE");
            $prices->execute([$id]);
            $prices = $prices->fetchAll(PDO::FETCH_ASSOC);
            $oldPrice = array_filter($prices, fn(array $price): bool => $price['module_slug'] === 'divulgacao');
            if ($rows[0]['slug'] === 'divulgacao' || $oldPrice) {
                $projects = $pdo->prepare("SELECT id FROM projects WHERE base_id=? AND status <> 'deleted' LIMIT 1");
                $projects->execute([$id]);
                if ($projects->fetchColumn() !== false) {
                    throw new RuntimeException('Esta renomeacao requer uma base sem projetos. Nenhum projeto foi alterado.');
                }
            }
            if ($oldPrice && count($prices) > 1) {
                throw new RuntimeException('Precos de Divulgacao e Landing Pages coexistem. Resolva o conflito antes de migrar.');
            }
            if ($apply) {
                baseRenameIdentity($pdo, 'divulgacao', 'marketing', 'Marketing');
                $update = $pdo->prepare("UPDATE base_module_prices SET module_slug='landing_page' WHERE base_id=? AND module_slug='divulgacao'");
                $update->execute([$id]);
            }
        }
        $apply ? $pdo->commit() : $pdo->rollBack();
        return $id;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
