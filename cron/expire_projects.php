<?php

require __DIR__ . '/../app/bootstrap/bootstrap.php';

/* =========================
EXPIRAR PROJETOS NÃO ATIVADOS
========================= */

$stmt = $pdo->prepare("
UPDATE projects
SET status = 'expired'
WHERE user_activated = 0
AND created_at < NOW() - INTERVAL 7 DAY
AND status = 'pending'
");

$stmt->execute();

echo "Projetos expirados: " . $stmt->rowCount();