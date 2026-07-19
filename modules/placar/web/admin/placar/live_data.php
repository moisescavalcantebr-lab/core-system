<?php
$stmt = $pdo->prepare("
    SELECT id, title, status
    FROM scoreboards
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$scoreboardId]);
$scoreboard = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$scoreboard) {
    http_response_code(404);
    echo json_encode(['error' => 'Placar nao encontrado.']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, label, score
    FROM scoreboard_items
    WHERE scoreboard_id = ?
    ORDER BY sort_order ASC, id ASC
    LIMIT 2
");
$stmt->execute([$scoreboardId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'id' => (int)$scoreboard['id'],
    'title' => $scoreboard['title'],
    'status' => $scoreboard['status'],
    'items' => array_map(static function (array $item): array {
        return [
            'id' => (int)$item['id'],
            'label' => $item['label'],
            'score' => (int)$item['score'],
        ];
    }, $items),
]);
exit;
