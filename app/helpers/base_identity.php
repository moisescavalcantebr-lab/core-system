<?php
declare(strict_types=1);

function baseManifestRenames(string $basesRoot): array
{
    $aliases = [];
    foreach (glob($basesRoot.'/*/base.json') ?: [] as $path) {
        $manifest = json_decode((string)file_get_contents($path),true);
        $old = $manifest['previous_slug'] ?? '';
        $new = $manifest['slug'] ?? '';
        if (!is_string($old) || !is_string($new) || $old === '') continue;
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/D',$old) || !preg_match('/^[a-z0-9][a-z0-9-]*$/D',$new) || $old === 'base' || $old === $new || basename(dirname($path)) !== $new) throw new RuntimeException('Alias de base invalido.');
        if (isset($aliases[$old]) && $aliases[$old] !== $new) throw new RuntimeException('Alias de base ambiguo.');
        $aliases[$old] = $new;
    }
    return $aliases;
}

function baseRenameIdentity(PDO $pdo, string $old, string $new, string $name): ?int
{
    $ownTransaction = !$pdo->inTransaction();
    if ($ownTransaction) $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id,slug FROM bases WHERE slug IN (?,?) ORDER BY id FOR UPDATE');
        $stmt->execute([$old,$new]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 1) throw new RuntimeException('Bases antiga e nova coexistem. Corrija o conflito antes de migrar.');
        $id = $rows ? (int)$rows[0]['id'] : null;
        if ($id && $rows[0]['slug'] === $old) {
            $update = $pdo->prepare('UPDATE bases SET slug=?,name=? WHERE id=?');
            $update->execute([$new,$name,$id]);
        }
        if ($ownTransaction) $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        if ($ownTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
