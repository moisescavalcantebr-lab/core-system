<?php

function playerPositionDefaults(): array
{
    return [
        ['code' => 'GO1', 'name' => 'Goleiro 1', 'group_key' => 'goleiro', 'group_label' => 'Goleiros', 'sort_order' => 10],
        ['code' => 'GO2', 'name' => 'Goleiro 2', 'group_key' => 'goleiro', 'group_label' => 'Goleiros', 'sort_order' => 11],
        ['code' => 'GO3', 'name' => 'Goleiro 3', 'group_key' => 'goleiro', 'group_label' => 'Goleiros', 'sort_order' => 12],
        ['code' => 'GO4', 'name' => 'Goleiro 4', 'group_key' => 'goleiro', 'group_label' => 'Goleiros', 'sort_order' => 13],

        ['code' => 'ZC1', 'name' => 'Zagueiro 1', 'group_key' => 'zagueiro', 'group_label' => 'Zagueiros', 'sort_order' => 20],
        ['code' => 'ZC2', 'name' => 'Zagueiro 2', 'group_key' => 'zagueiro', 'group_label' => 'Zagueiros', 'sort_order' => 21],
        ['code' => 'ZC3', 'name' => 'Zagueiro 3', 'group_key' => 'zagueiro', 'group_label' => 'Zagueiros', 'sort_order' => 22],
        ['code' => 'ZC4', 'name' => 'Zagueiro 4', 'group_key' => 'zagueiro', 'group_label' => 'Zagueiros', 'sort_order' => 23],

        ['code' => 'LE1', 'name' => 'Lateral Esquerdo 1', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 30],
        ['code' => 'LE2', 'name' => 'Lateral Esquerdo 2', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 31],
        ['code' => 'LE3', 'name' => 'Lateral Esquerdo 3', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 32],
        ['code' => 'LD1', 'name' => 'Lateral Direito 1', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 33],
        ['code' => 'LD2', 'name' => 'Lateral Direito 2', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 34],
        ['code' => 'LD3', 'name' => 'Lateral Direito 3', 'group_key' => 'lateral', 'group_label' => 'Laterais', 'sort_order' => 35],

        ['code' => 'VOL1', 'name' => 'Volante 1', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 40],
        ['code' => 'VOL2', 'name' => 'Volante 2', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 41],
        ['code' => 'VOL3', 'name' => 'Volante 3', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 42],
        ['code' => 'MAT1', 'name' => 'Meia Atacante 1', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 43],
        ['code' => 'MAT2', 'name' => 'Meia Atacante 2', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 44],
        ['code' => 'MAT3', 'name' => 'Meia Atacante 3', 'group_key' => 'meia', 'group_label' => 'Meias', 'sort_order' => 45],

        ['code' => 'PTE1', 'name' => 'Ponta Esquerda 1', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 50],
        ['code' => 'PTE2', 'name' => 'Ponta Esquerda 2', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 51],
        ['code' => 'PTE3', 'name' => 'Ponta Esquerda 3', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 52],
        ['code' => 'PTD1', 'name' => 'Ponta Direita 1', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 53],
        ['code' => 'PTD2', 'name' => 'Ponta Direita 2', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 54],
        ['code' => 'PTD3', 'name' => 'Ponta Direita 3', 'group_key' => 'ponta', 'group_label' => 'Pontas', 'sort_order' => 55],

        ['code' => 'CA1', 'name' => 'Atacante 1', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 60],
        ['code' => 'CA2', 'name' => 'Atacante 2', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 61],
        ['code' => 'CA3', 'name' => 'Atacante 3', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 62],
        ['code' => 'CA4', 'name' => 'Atacante 4', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 63],
    ];
}

function playerPositionGroups(): array
{
    return [
        'goleiro' => 'Goleiros',
        'zagueiro' => 'Zagueiros',
        'lateral' => 'Laterais',
        'meia' => 'Meias',
        'ponta' => 'Pontas',
        'atacante' => 'Atacantes',
    ];
}

function playerNicknameLimit(): int
{
    return 14;
}

function playerNicknameLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function playerNicknameSlice(string $value): string
{
    return function_exists('mb_substr')
        ? mb_substr($value, 0, playerNicknameLimit())
        : substr($value, 0, playerNicknameLimit());
}

function playerDisplayName(?string $nickname, string $name): string
{
    $nickname = trim((string)$nickname);
    if ($nickname !== '') {
        return playerNicknameSlice($nickname);
    }

    $name = trim($name);
    if ($name === '') {
        return 'Jogador';
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    return playerNicknameSlice((string)($parts[0] ?? $name));
}

function playerNicknameValue(?string $nickname, string $name): string
{
    return playerDisplayName($nickname, $name);
}

function playerValidateNickname(?string $nickname): ?string
{
    $nickname = trim((string)$nickname);

    if ($nickname !== '' && playerNicknameLength($nickname) > playerNicknameLimit()) {
        return 'O apelido deve ter no maximo ' . playerNicknameLimit() . ' caracteres.';
    }

    return null;
}

function playerProjectPlanKey(): string
{
    global $project;

    $planName = strtolower((string)($project['plan_name'] ?? ''));
    $cycle = strtolower((string)($project['billing_cycle'] ?? ''));

    if ($cycle === 'free' || str_contains($planName, 'gratis') || str_contains($planName, 'grátis')) {
        return 'free';
    }

    if (str_contains($planName, 'plus') || $cycle === 'annual') {
        return 'plus';
    }

    if (str_contains($planName, 'start') || $cycle === 'monthly') {
        return 'start';
    }

    return $cycle !== '' ? $cycle : 'free';
}

function playerAccessFeatureEnabled(): bool
{
    return playerProjectPlanKey() !== 'free';
}

function playerAccessLabel(?int $userId, ?string $userStatus): string
{
    return $userId !== null && $userId > 0 && $userStatus === 'active' ? 'Liberado' : 'Admin';
}

function playerAccessBadge(?int $userId, ?string $userStatus): string
{
    return playerAccessLabel($userId, $userStatus) === 'Liberado' ? 'success' : 'neutral';
}

function playerEnsurePositionSchema(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM player_positions")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('code', $columns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN code VARCHAR(20) NULL AFTER id");
    }

    if (!in_array('group_key', $columns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN group_key VARCHAR(40) NULL AFTER name");
    }

    if (!in_array('group_label', $columns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN group_label VARCHAR(80) NULL AFTER group_key");
    }

    if (!in_array('sort_order', $columns, true)) {
        $pdo->exec("ALTER TABLE player_positions ADD COLUMN sort_order INT DEFAULT 0 AFTER status");
    }

    $playerColumns = $pdo->query("SHOW COLUMNS FROM players")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('participant_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN participant_id INT NULL AFTER id");
        $pdo->exec("ALTER TABLE players ADD UNIQUE KEY unique_player_participant (participant_id)");
    }

    if (!in_array('user_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN user_id INT NULL AFTER id");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_user_id (user_id)");
    }

    if (!in_array('nickname', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN nickname VARCHAR(14) NULL AFTER name");
    } else {
        $pdo->exec("ALTER TABLE players MODIFY COLUMN nickname VARCHAR(14) NULL AFTER name");
    }

    if (!in_array('avatar', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN avatar VARCHAR(255) NULL AFTER nickname");
    }

    if (!in_array('position_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN position_id INT NULL AFTER whatsapp");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_position_id (position_id)");
    }

    if (!in_array('secondary_position_id', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN secondary_position_id INT NULL AFTER position_id");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_secondary_position (secondary_position_id)");
    }

    if (!in_array('roster_status', $playerColumns, true)) {
        $pdo->exec("ALTER TABLE players ADD COLUMN roster_status ENUM('titular','reserva') DEFAULT 'titular' AFTER position");
        $pdo->exec("ALTER TABLE players ADD INDEX idx_players_roster_status (roster_status)");
    }
}

function playerRosterStatusLabel(?string $status): string
{
    return $status === 'reserva' ? 'RES' : 'TIT';
}

function playerRosterStatusBadge(?string $status): string
{
    return $status === 'reserva' ? 'warning' : 'success';
}

function playerUploadAvatar(array $file, int $playerId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha ao enviar avatar.');
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    $mime = (string)($file['type'] ?? '');
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, (string)$file['tmp_name']);
            finfo_close($finfo);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
        }
    }

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de avatar invalido. Use JPG, PNG ou WEBP.');
    }

    if ((int)($file['size'] ?? 0) > 3 * 1024 * 1024) {
        throw new RuntimeException('Avatar muito grande. Envie uma imagem de ate 3 MB.');
    }

    $relativeFolder = 'storage/uploads/avatars';
    $absoluteFolder = PUBLIC_PATH . '/' . $relativeFolder;

    if (!is_dir($absoluteFolder)) {
        mkdir($absoluteFolder, 0755, true);
    }

    $fileName = 'player-' . $playerId . '-' . time() . '.' . $allowed[$mime];
    $relativePath = $relativeFolder . '/' . $fileName;
    $destination = PUBLIC_PATH . '/' . $relativePath;

    if (!move_uploaded_file((string)$file['tmp_name'], $destination)) {
        throw new RuntimeException('Nao foi possivel salvar o avatar.');
    }

    return $relativePath;
}

function playerDeleteAvatarFile(?string $avatar): void
{
    $avatar = trim((string)$avatar);

    if ($avatar === '' || str_contains($avatar, '..') || !str_starts_with($avatar, 'storage/uploads/avatars/')) {
        return;
    }

    $path = PUBLIC_PATH . '/' . $avatar;
    if (is_file($path)) {
        @unlink($path);
    }
}

function playerParticipantsAvailable(PDO $pdo): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE 'participants'");

    return (bool)$stmt->fetchColumn();
}

function playerParticipantStatus(string $playerStatus): string
{
    return $playerStatus === 'active' ? 'active' : 'inactive';
}

function playerSyncParticipant(
    PDO $pdo,
    ?int $participantId,
    ?int $userId,
    string $name,
    string $nickname,
    ?string $whatsapp,
    ?string $birthDate,
    string $status,
    ?string $notes = null
): ?int {
    if (!playerParticipantsAvailable($pdo)) {
        return null;
    }

    $participantStatus = playerParticipantStatus($status);
    $nickname = trim($nickname) !== '' ? playerNicknameValue($nickname, $name) : null;

    if ($participantId !== null && $participantId > 0) {
        $stmt = $pdo->prepare("
            UPDATE participants
            SET user_id = ?,
                name = ?,
                nickname = ?,
                whatsapp = ?,
                birth_date = ?,
                status = ?,
                notes = COALESCE(?, notes)
            WHERE id = ?
        ");
        $stmt->execute([$userId, $name, $nickname, $whatsapp, $birthDate, $participantStatus, $notes, $participantId]);

        return $participantId;
    }

    if ($userId !== null && $userId > 0) {
        $stmt = $pdo->prepare("SELECT id FROM participants WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $existingId = (int)($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            return playerSyncParticipant($pdo, $existingId, $userId, $name, (string)$nickname, $whatsapp, $birthDate, $status, $notes);
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO participants (user_id, name, nickname, whatsapp, birth_date, status, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $name, $nickname, $whatsapp, $birthDate, $participantStatus, $notes]);

    return (int)$pdo->lastInsertId();
}

function playerEnsureDefaultPositions(PDO $pdo): void
{
    playerEnsurePositionSchema($pdo);
    playerMigrateSecondStrikerPositions($pdo);
    playerReleaseInactiveRosterSlots($pdo);

    $stmt = $pdo->prepare("
        INSERT INTO player_positions (code, name, group_key, group_label, sort_order, status)
        VALUES (?, ?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            group_key = VALUES(group_key),
            group_label = VALUES(group_label),
            sort_order = VALUES(sort_order),
            status = 'active'
    ");

    foreach (playerPositionDefaults() as $position) {
        $existing = $pdo->prepare("
            SELECT id
            FROM player_positions
            WHERE code = ? OR name = ?
            ORDER BY CASE WHEN code = ? THEN 0 ELSE 1 END, id ASC
        ");
        $existing->execute([$position['code'], $position['name'], $position['code']]);
        $matches = array_map('intval', $existing->fetchAll(PDO::FETCH_COLUMN));

        if (!empty($matches)) {
            $targetId = array_shift($matches);

            foreach ($matches as $duplicateId) {
                playerMergePosition($pdo, $duplicateId, $targetId);
            }

            $update = $pdo->prepare("
                UPDATE player_positions
                SET code = ?, name = ?, group_key = ?, group_label = ?, sort_order = ?, status = 'active'
                WHERE id = ?
            ");
            $update->execute([
                $position['code'],
                $position['name'],
                $position['group_key'],
                $position['group_label'],
                $position['sort_order'],
                $targetId,
            ]);
            continue;
        }

        $stmt->execute([
            $position['code'],
            $position['name'],
            $position['group_key'],
            $position['group_label'],
            $position['sort_order'],
        ]);
    }

    $codes = array_column(playerPositionDefaults(), 'code');
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $legacy = $pdo->prepare("UPDATE player_positions SET status = 'inactive' WHERE (code IS NULL OR code NOT IN ({$placeholders}))");
    $legacy->execute($codes);
}

function playerReleaseInactiveRosterSlots(PDO $pdo): void
{
    $pdo->exec("
        UPDATE players
        SET position_id = NULL,
            secondary_position_id = NULL,
            shirt_number = NULL
        WHERE status = 'inactive'
          AND (position_id IS NOT NULL OR secondary_position_id IS NOT NULL OR shirt_number IS NOT NULL)
    ");
}

function playerMergePosition(PDO $pdo, int $fromId, int $toId): void
{
    if ($fromId <= 0 || $toId <= 0 || $fromId === $toId) {
        return;
    }

    $movePrimary = $pdo->prepare("UPDATE players SET position_id = ? WHERE position_id = ?");
    $movePrimary->execute([$toId, $fromId]);

    $moveSecondary = $pdo->prepare("UPDATE players SET secondary_position_id = ? WHERE secondary_position_id = ?");
    $moveSecondary->execute([$toId, $fromId]);

    try {
        $delete = $pdo->prepare("DELETE FROM player_positions WHERE id = ?");
        $delete->execute([$fromId]);
    } catch (Throwable $e) {
        $archive = $pdo->prepare("
            UPDATE player_positions
            SET code = CONCAT('LEGACY', id),
                name = CONCAT(name, ' antigo ', id),
                status = 'inactive'
            WHERE id = ?
        ");
        $archive->execute([$fromId]);
    }
}

function playerMigrateSecondStrikerPositions(PDO $pdo): void
{
    $migrations = [
        'SA1' => ['code' => 'CA3', 'name' => 'Atacante 3', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 62],
        'SA2' => ['code' => 'CA4', 'name' => 'Atacante 4', 'group_key' => 'atacante', 'group_label' => 'Atacantes', 'sort_order' => 63],
    ];

    foreach ($migrations as $oldCode => $newPosition) {
        $oldStmt = $pdo->prepare("SELECT id FROM player_positions WHERE code = ? LIMIT 1");
        $oldStmt->execute([$oldCode]);
        $oldId = $oldStmt->fetchColumn();

        if (!$oldId) {
            continue;
        }

        $newStmt = $pdo->prepare("SELECT id FROM player_positions WHERE code = ? LIMIT 1");
        $newStmt->execute([$newPosition['code']]);
        $newId = $newStmt->fetchColumn();

        if (!$newId) {
            $update = $pdo->prepare("
                UPDATE player_positions
                SET code = ?, name = ?, group_key = ?, group_label = ?, sort_order = ?, status = 'active'
                WHERE id = ?
            ");
            $update->execute([
                $newPosition['code'],
                $newPosition['name'],
                $newPosition['group_key'],
                $newPosition['group_label'],
                $newPosition['sort_order'],
                (int)$oldId,
            ]);
            continue;
        }

        $movePrimary = $pdo->prepare("UPDATE players SET position_id = ? WHERE position_id = ?");
        $movePrimary->execute([(int)$newId, (int)$oldId]);

        $moveSecondary = $pdo->prepare("UPDATE players SET secondary_position_id = ? WHERE secondary_position_id = ?");
        $moveSecondary->execute([(int)$newId, (int)$oldId]);

        $disable = $pdo->prepare("UPDATE player_positions SET status = 'inactive' WHERE id = ?");
        $disable->execute([(int)$oldId]);
    }
}

function playerActiveLimit(): int
{
    return function_exists('projectPlanLimit') ? projectPlanLimit('players_active', 30) : 30;
}

function playerValidateShirtNumber(PDO $pdo, ?int $shirtNumber, ?int $ignorePlayerId = null, string $targetStatus = 'active'): ?string
{
    if ($shirtNumber === null) {
        return null;
    }

    if ($shirtNumber < 1 || $shirtNumber > 99) {
        return 'Numero da camisa deve ser entre 1 e 99.';
    }

    if ($targetStatus !== 'active') {
        return null;
    }

    $params = [$shirtNumber];
    $where = "shirt_number = ? AND status = 'active'";

    if ($ignorePlayerId !== null) {
        $where .= ' AND id <> ?';
        $params[] = $ignorePlayerId;
    }

    $stmt = $pdo->prepare("SELECT id FROM players WHERE {$where} LIMIT 1");
    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        return 'Este numero de camisa ja esta em uso.';
    }

    return null;
}

function playerAvailablePositions(PDO $pdo, ?int $ignorePlayerId = null): array
{
    $params = [];
    $ignoreSql = '';

    if ($ignorePlayerId !== null) {
        $ignoreSql = ' AND p.id <> ?';
        $params[] = $ignorePlayerId;
    }

    $stmt = $pdo->prepare("
        SELECT pp.*
        FROM player_positions pp
        WHERE pp.status = 'active'
          AND NOT EXISTS (
              SELECT 1
              FROM players p
              WHERE p.status = 'active'
                AND p.position_id = pp.id
                {$ignoreSql}
          )
        ORDER BY pp.sort_order ASC, pp.name ASC
    ");
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function playerAvailableShirtNumbers(PDO $pdo, ?int $ignorePlayerId = null): array
{
    $params = [];
    $ignoreSql = '';

    if ($ignorePlayerId !== null) {
        $ignoreSql = ' AND id <> ?';
        $params[] = $ignorePlayerId;
    }

    $stmt = $pdo->prepare("
        SELECT shirt_number
        FROM players
        WHERE status = 'active'
          AND shirt_number IS NOT NULL
          {$ignoreSql}
    ");
    $stmt->execute($params);
    $used = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $available = [];
    for ($number = 1; $number <= 99; $number++) {
        if (!in_array($number, $used, true)) {
            $available[] = $number;
        }
    }

    return $available;
}

function playerValidateActiveRoster(PDO $pdo, string $status, ?int $positionId, ?int $ignorePlayerId = null): ?string
{
    if ($status !== 'active') {
        return null;
    }

    $params = [];
    $where = "status = 'active'";

    if ($ignorePlayerId !== null) {
        $where .= " AND id <> ?";
        $params[] = $ignorePlayerId;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE {$where}");
    $stmt->execute($params);

    if ((int)$stmt->fetchColumn() >= playerActiveLimit()) {
        return 'Limite de ' . playerActiveLimit() . ' jogadores ativos no elenco atingido. Desative outro jogador para ativar este.';
    }

    if ($positionId !== null) {
        $params = [$positionId];
        $where = "status = 'active' AND position_id = ?";

        if ($ignorePlayerId !== null) {
            $where .= " AND id <> ?";
            $params[] = $ignorePlayerId;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM players WHERE {$where}");
        $stmt->execute($params);

        if ((int)$stmt->fetchColumn() > 0) {
            return 'Esta posicao ja esta ocupada por outro jogador ativo.';
        }
    }

    return null;
}
