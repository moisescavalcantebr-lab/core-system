<?php

function financeMoney(float $value): string
{
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function financeEnsureEntryMetaSchema(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM finance_entries")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('payment_method', $columns, true)) {
        $pdo->exec("ALTER TABLE finance_entries ADD COLUMN payment_method VARCHAR(40) NULL AFTER source");
    }

    if (!in_array('receipt_path', $columns, true)) {
        $pdo->exec("ALTER TABLE finance_entries ADD COLUMN receipt_path VARCHAR(255) NULL AFTER payment_method");
    }

    $partyType = $pdo->query("SHOW COLUMNS FROM finance_entries LIKE 'party_type'")->fetch(PDO::FETCH_ASSOC);
    $columnType = (string)($partyType['Type'] ?? '');

    if ($columnType !== '' && !str_contains($columnType, "'user'")) {
        $pdo->exec("ALTER TABLE finance_entries MODIFY party_type ENUM('user','admin','supplier','customer','member','other') DEFAULT 'other'");
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_wallet_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            receipt_path VARCHAR(255) NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            reviewed_by_user_id INT NULL,
            reviewed_at DATETIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX(user_id),
            INDEX(status),
            INDEX(reviewed_by_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $walletUserColumn = $pdo->query("SHOW COLUMNS FROM finance_wallet_requests LIKE 'user_id'")->fetch(PDO::FETCH_ASSOC);
    if ($walletUserColumn && !str_contains(strtoupper((string)($walletUserColumn['Null'] ?? '')), 'YES')) {
        try {
            $pdo->exec("ALTER TABLE finance_wallet_requests MODIFY user_id INT NULL");
        } catch (Throwable $e) {
            // Older installs may keep a foreign key here; keeping the requester id is still compatible.
        }
    }
}

function financeEnsureCategoryAddonSchema(PDO $pdo): void
{
    $categoryColumns = $pdo->query("SHOW COLUMNS FROM finance_categories")->fetchAll(PDO::FETCH_COLUMN);
    $addedFormModelColumn = false;

    if (!in_array('parent_id', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE finance_categories ADD COLUMN parent_id INT NULL AFTER id");
        $pdo->exec("ALTER TABLE finance_categories ADD INDEX idx_finance_categories_parent (parent_id)");
    }

    if (!in_array('template_key', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE finance_categories ADD COLUMN template_key VARCHAR(120) NULL AFTER type");
    }

    if (!in_array('is_system', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE finance_categories ADD COLUMN is_system TINYINT(1) DEFAULT 0 AFTER template_key");
    }

    if (!in_array('sort_order', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE finance_categories ADD COLUMN sort_order INT DEFAULT 0 AFTER is_system");
        $pdo->exec("ALTER TABLE finance_categories ADD INDEX idx_finance_categories_sort (sort_order)");
    }

    if (!in_array('form_model', $categoryColumns, true)) {
        $pdo->exec("ALTER TABLE finance_categories ADD COLUMN form_model ENUM('simple','installment','recurring') DEFAULT 'simple' AFTER type");
        $pdo->exec("ALTER TABLE finance_categories ADD INDEX idx_finance_categories_form_model (form_model)");
        $addedFormModelColumn = true;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(80) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX(status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS finance_entry_tags (
            entry_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (entry_id, tag_id),
            INDEX(tag_id),
            CONSTRAINT fk_finance_entry_tags_entry
                FOREIGN KEY (entry_id)
                REFERENCES finance_entries(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_finance_entry_tags_tag
                FOREIGN KEY (tag_id)
                REFERENCES finance_tags(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    financeRemoveLegacyDefaultCategories($pdo);

    if ($addedFormModelColumn) {
        financeBackfillCategoryFormModels($pdo);
    }
}

function financeSlug(string $value): string
{
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'tag-' . substr(md5($value), 0, 8);
}

function financeCategoryLabel(array $category): string
{
    $parentName = (string)($category['parent_name'] ?? '');
    $name = (string)($category['name'] ?? '');

    return $parentName !== '' ? $parentName . ' > ' . $name : $name;
}

function financeFormModelOptions(): array
{
    return [
        'simple' => 'Simples',
        'installment' => 'Parcelado',
        'recurring' => 'Recorrente',
    ];
}

function financeFormModelLabel(?string $model): string
{
    return financeFormModelOptions()[$model ?? ''] ?? 'Simples';
}

function financeNormalizeFormModel(?string $model): string
{
    return in_array($model, ['simple', 'installment', 'recurring'], true) ? $model : 'simple';
}

function financeInferFormModel(string $name): string
{
    $normalized = financeSlug($name);

    foreach (['financiamento', 'financiamentos', 'emprestimo', 'emprestimos', 'parcelamento', 'parcelamentos'] as $term) {
        if (str_contains($normalized, $term)) {
            return 'installment';
        }
    }

    foreach (['aluguel', 'condominio', 'internet', 'energia', 'agua', 'gas', 'plano-de-saude', 'academia', 'streaming', 'hospedagem', 'dominios', 'softwares', 'seguro'] as $term) {
        if (str_contains($normalized, $term)) {
            return 'recurring';
        }
    }

    return 'simple';
}

function financeBackfillCategoryFormModels(PDO $pdo): void
{
    $categories = $pdo->query("SELECT id, name FROM finance_categories")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("UPDATE finance_categories SET form_model = ? WHERE id = ?");

    foreach ($categories as $category) {
        $stmt->execute([
            financeInferFormModel((string)($category['name'] ?? '')),
            (int)$category['id'],
        ]);
    }
}

function financeCategoryOptions(PDO $pdo, ?bool $includeSubcategories = null): array
{
    financeEnsureCategoryAddonSchema($pdo);

    $includeSubcategories = $includeSubcategories ?? financeAdvancedCategoriesEnabled();
    $where = $includeSubcategories
        ? "c.status = 'active'"
        : "c.status = 'active' AND c.parent_id IS NULL AND COALESCE(c.is_system, 0) = 0";

    return $pdo->query("
        SELECT c.*, p.name AS parent_name
        FROM finance_categories c
        LEFT JOIN finance_categories p ON p.id = c.parent_id
        WHERE {$where}
        ORDER BY COALESCE(p.sort_order, c.sort_order), COALESCE(p.name, c.name), c.parent_id IS NOT NULL, c.sort_order, c.name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

function financeTagNames(PDO $pdo, int $entryId): array
{
    financeEnsureCategoryAddonSchema($pdo);

    $stmt = $pdo->prepare("
        SELECT t.name
        FROM finance_tags t
        INNER JOIN finance_entry_tags et ON et.tag_id = t.id
        WHERE et.entry_id = ?
        ORDER BY t.name ASC
    ");
    $stmt->execute([$entryId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function financeSyncEntryTags(PDO $pdo, int $entryId, string $tagsText): void
{
    financeEnsureCategoryAddonSchema($pdo);

    if (!financeAdvancedCategoriesEnabled()) {
        return;
    }

    $parts = preg_split('/[,;\n]+/', $tagsText) ?: [];
    $tags = [];

    foreach ($parts as $part) {
        $name = trim((string)$part);
        if ($name !== '') {
            $key = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            $tags[$key] = $name;
        }
    }

    $pdo->prepare("DELETE FROM finance_entry_tags WHERE entry_id = ?")->execute([$entryId]);

    if (empty($tags)) {
        return;
    }

    $tagStmt = $pdo->prepare("
        INSERT INTO finance_tags (name, slug, status)
        VALUES (?, ?, 'active')
        ON DUPLICATE KEY UPDATE name = VALUES(name), status = 'active'
    ");
    $findStmt = $pdo->prepare("SELECT id FROM finance_tags WHERE slug = ? LIMIT 1");
    $linkStmt = $pdo->prepare("
        INSERT IGNORE INTO finance_entry_tags (entry_id, tag_id)
        VALUES (?, ?)
    ");

    foreach ($tags as $name) {
        $slug = financeSlug($name);
        $tagStmt->execute([$name, $slug]);
        $findStmt->execute([$slug]);
        $tagId = (int)$findStmt->fetchColumn();

        if ($tagId > 0) {
            $linkStmt->execute([$entryId, $tagId]);
        }
    }
}

function financePersonalCategoryTemplate(): array
{
    return [
        ['Moradia', 'expense', ['Aluguel', 'Financiamento', 'Condomínio', 'IPTU', 'Energia', 'Água', 'Gás', 'Internet', 'Manutenção', 'Seguro Residencial']],
        ['Alimentação', 'expense', ['Supermercado', 'Padaria', 'Açougue', 'Hortifruti', 'Restaurante', 'Delivery', 'Lanches', 'Café']],
        ['Transporte', 'expense', ['Combustível', 'Transporte Público', 'Uber / Táxi', 'Estacionamento', 'Pedágio', 'Seguro Veículo', 'Manutenção Veículo', 'Financiamento Veículo']],
        ['Saúde', 'expense', ['Plano de Saúde', 'Consultas', 'Exames', 'Medicamentos', 'Dentista', 'Academia', 'Terapias']],
        ['Educação', 'expense', ['Escola', 'Faculdade', 'Cursos', 'Livros', 'Materiais', 'Certificações']],
        ['Lazer', 'expense', ['Streaming', 'Cinema', 'Viagens', 'Jogos', 'Eventos', 'Hobbies', 'Passeios']],
        ['Compras', 'expense', ['Vestuário', 'Calçados', 'Acessórios', 'Eletrônicos', 'Casa e Decoração', 'Presentes']],
        ['Família', 'expense', ['Filhos', 'Pets', 'Mesada', 'Cuidados Domésticos']],
        ['Investimentos', 'income', ['Bitcoin', 'Ethereum', 'Ações', 'Fundos', 'Renda Fixa', 'Reserva de Emergência']],
        ['Dívidas', 'expense', ['Cartão de Crédito', 'Empréstimos', 'Financiamentos', 'Parcelamentos']],
        ['Trabalho', 'both', ['Ferramentas', 'Hospedagem', 'Domínios', 'Softwares', 'Marketing', 'Equipamentos']],
    ];
}

function financePlayerCategoryTemplate(): array
{
    return [
        ['Mensalidades', 'income', ['Mensalidade mensal', 'Mensalidade atrasada', 'Taxa de inscrição']],
        ['Uniformes', 'expense', ['Camisa', 'Calção', 'Meião', 'Kit completo', 'Reposição']],
        ['Campo e Quadra', 'expense', ['Aluguel do campo', 'Aluguel da quadra', 'Iluminação', 'Reserva de horário']],
        ['Arbitragem', 'expense', ['Árbitro', 'Mesário', 'Taxa de jogo']],
        ['Material esportivo', 'expense', ['Bolas', 'Coletes', 'Cones', 'Redes', 'Materiais de treino']],
        ['Inscrições', 'expense', ['Campeonato', 'Torneio', 'Liga', 'Federação']],
        ['Patrocínios', 'income', ['Patrocínio fixo', 'Apoio pontual', 'Doação']],
        ['Transporte', 'expense', ['Combustível', 'Van', 'Ônibus', 'Pedágio', 'Estacionamento']],
        ['Eventos', 'both', ['Confraternização', 'Rifa', 'Churrasco', 'Ação entre amigos']],
        ['Premiações', 'expense', ['Troféu', 'Medalhas', 'Premiação em dinheiro']],
        ['Multas e Taxas', 'income', ['Multa por falta', 'Taxa extra', 'Reposição de caixa']],
        ['Outros', 'both', ['Receitas diversas', 'Despesas diversas']],
    ];
}

function financeCategoryTemplates(): array
{
    return [
        'financeiro_pessoal' => [
            'label' => 'Financeiro pessoal',
            'description' => 'Categorias para caixa pessoal, casa, trabalho, compras e investimentos.',
            'modes' => ['personal'],
            'groups' => financePersonalCategoryTemplate(),
        ],
        'financeiro_jogador' => [
            'label' => 'Financeiro jogador/time',
            'description' => 'Categorias para mensalidades, uniformes, campo, arbitragem e organização do time.',
            'modes' => ['jogador', 'jogadores', 'player', 'players'],
            'groups' => financePlayerCategoryTemplate(),
        ],
    ];
}

function financeRecommendedCategoryTemplate(PDO $pdo): string
{
    $mode = financeMode($pdo);

    foreach (financeCategoryTemplates() as $key => $template) {
        if (in_array($mode, $template['modes'] ?? [], true)) {
            return $key;
        }
    }

    return 'financeiro_pessoal';
}

function financeRemoveLegacyDefaultCategories(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        DELETE FROM finance_categories
        WHERE name IN ('Geral', 'Receitas', 'Despesas')
          AND parent_id IS NULL
          AND id NOT IN (
              SELECT category_id
              FROM finance_entries
              WHERE category_id IS NOT NULL
          )
    ");
    $stmt->execute();
}

function financeSeedCategoryTemplate(PDO $pdo, string $templateKey): int
{
    financeEnsureCategoryAddonSchema($pdo);

    $templates = financeCategoryTemplates();
    $template = $templates[$templateKey] ?? $templates['financeiro_pessoal'];
    $findStmt = $pdo->prepare("SELECT id FROM finance_categories WHERE name = ? LIMIT 1");
    $insertStmt = $pdo->prepare("
        INSERT INTO finance_categories (parent_id, name, type, form_model, template_key, is_system, sort_order, status)
        VALUES (?, ?, ?, ?, ?, 1, ?, 'active')
    ");

    $created = 0;
    $order = 10;

    foreach (($template['groups'] ?? []) as $group) {
        [$parentName, $type, $children] = $group;

        $findStmt->execute([$parentName]);
        $parentId = (int)$findStmt->fetchColumn();

        if ($parentId <= 0) {
            $insertStmt->execute([null, $parentName, $type, financeInferFormModel($parentName), $templateKey, $order]);
            $parentId = (int)$pdo->lastInsertId();
            $created++;
        }

        $childOrder = $order + 1;
        foreach ($children as $childName) {
            $findStmt->execute([$childName]);
            if (!$findStmt->fetchColumn()) {
                $insertStmt->execute([$parentId, $childName, $type, financeInferFormModel($childName), $templateKey, $childOrder]);
                $created++;
            }
            $childOrder++;
        }

        $order += 100;
    }

    return $created;
}

function financeSeedPersonalCategories(PDO $pdo): int
{
    return financeSeedCategoryTemplate($pdo, 'financeiro_pessoal');
}

function financeProjectPlanData(): array
{
    $projectConfigPath = defined('PROJECT_PATH') ? PROJECT_PATH . '/project.json' : '';

    if ($projectConfigPath === '' || !is_file($projectConfigPath)) {
        return [];
    }

    $config = json_decode((string)file_get_contents($projectConfigPath), true);

    return is_array($config) ? $config : [];
}

function financeAdvancedCategoriesEnabled(): bool
{
    $project = financeProjectPlanData();
    $cycle = strtolower((string)($project['billing_cycle'] ?? ''));
    $planName = strtolower((string)($project['plan_name'] ?? ''));

    if ($cycle === 'free' || str_contains($planName, 'gratis') || str_contains($planName, 'grátis')) {
        return false;
    }

    return $cycle !== '' || str_contains($planName, 'start') || str_contains($planName, 'plus');
}

function financeProjectIsFreePlan(): bool
{
    $project = financeProjectPlanData();
    $cycle = strtolower((string)($project['billing_cycle'] ?? ''));
    $planName = strtolower((string)($project['plan_name'] ?? ''));

    return $cycle === 'free'
        || str_contains($planName, 'gratis')
        || str_contains($planName, 'grátis')
        || ($cycle === '' && $planName === '');
}

function financeSimpleAdminMode(PDO $pdo): bool
{
    return financeProjectIsFreePlan();
}

function financePaymentMethodOptions(): array
{
    return [
        'pix' => 'Pix',
        'cash' => 'Dinheiro',
        'card' => 'Cartao',
        'transfer' => 'Transferencia',
        'other' => 'Outro',
    ];
}

function financePaymentMethodLabel(?string $method): string
{
    return financePaymentMethodOptions()[$method ?? ''] ?? '-';
}

function financeReceiptUpload(array $file, string $prefix = 'receipt'): ?string
{
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed, true)) {
        throw new RuntimeException('Formato de comprovante invalido.');
    }

    $uploadDir = STORAGE_PATH . '/uploads/finance_receipts/';

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Nao foi possivel preparar a pasta de comprovantes.');
    }

    $fileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Nao foi possivel salvar o comprovante.');
    }

    return 'storage/uploads/finance_receipts/' . $fileName;
}

function financeTypeLabel(string $type): string
{
    return $type === 'income' ? 'Entrada' : 'Saida';
}

function financeEntryTypeLabel(array $entry): string
{
    $source = (string)($entry['source'] ?? '');

    if ($source === 'balance_deposit') {
        return 'Saldo adicionado';
    }

    if ($source === 'balance_usage') {
        return 'Saldo utilizado';
    }

    return financeTypeLabel((string)($entry['type'] ?? 'expense'));
}

function financeStatusLabel(string $status): string
{
    return match ($status) {
        'paid' => 'Pago',
        'canceled' => 'Cancelado',
        default => 'Pendente',
    };
}

function financeStatusBadge(string $status): string
{
    return match ($status) {
        'paid' => 'c-badge--success',
        'canceled' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function financePartyTypeLabel(?string $type): string
{
    return match ($type) {
        'user' => financeUserLabel(),
        'admin' => 'Admin',
        'supplier' => 'Fornecedor',
        'customer' => 'Cliente',
        'member' => 'Membro',
        default => 'Outro',
    };
}

function financeUserLabel(bool $plural = false): string
{
    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO && financeUsesParticipants($pdo) && function_exists('getSetting')) {
        $participantLabel = getSetting($plural ? 'participant_label_plural' : 'participant_label', '');

        if ($participantLabel !== '') {
            return $participantLabel;
        }
    }

    $key = $plural ? 'finance_user_label_plural' : 'finance_user_label';
    $default = $plural ? 'Usuários' : 'Usuário';

    return isset($pdo) && $pdo instanceof PDO
        ? financeSetting($pdo, $key, $default)
        : $default;
}

function financeMode(PDO $pdo): string
{
    $mode = financeSetting($pdo, 'finance_mode', 'personal');
    $mode = financeSlug($mode);

    if (in_array($mode, ['', 'custom', 'participante', 'participantes'], true)) {
        return 'participants';
    }

    if ($mode === 'participants' && function_exists('getSetting')) {
        $participantContext = financeSlug(getSetting('participant_context', ''));

        if ($participantContext !== '' && !in_array($participantContext, ['custom', 'participante', 'participantes'], true)) {
            return $participantContext;
        }
    }

    return $mode === 'personal' ? 'personal' : $mode;
}

function financeUsesParticipants(PDO $pdo): bool
{
    return financeMode($pdo) !== 'personal';
}

function financeModeLabel(PDO $pdo): string
{
    if (!financeUsesParticipants($pdo)) {
        return 'Pessoal';
    }

    $label = financeUserLabel();

    return $label !== '' ? $label : 'Participantes';
}

function financeUserBalance(PDO $pdo, int $userId): float
{
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' AND source = 'balance_deposit' THEN amount ELSE 0 END), 0) AS deposited_total,
            COALESCE(SUM(CASE WHEN type = 'expense' AND source = 'balance_usage' THEN amount ELSE 0 END), 0) AS used_total
        FROM finance_entries
        WHERE party_type = 'user'
          AND party_id = ?
          AND status = 'paid'
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['deposited_total' => 0, 'used_total' => 0];

    return (float)$row['deposited_total'] - (float)$row['used_total'];
}

function financeAllocatedBalance(PDO $pdo): float
{
    $row = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income' AND source = 'balance_deposit' THEN amount ELSE 0 END), 0) AS deposited_total,
            COALESCE(SUM(CASE WHEN type = 'expense' AND source = 'balance_usage' THEN amount ELSE 0 END), 0) AS used_total
        FROM finance_entries
        WHERE status = 'paid'
    ")->fetch(PDO::FETCH_ASSOC) ?: ['deposited_total' => 0, 'used_total' => 0];

    return (float)$row['deposited_total'] - (float)$row['used_total'];
}

function financeCorePdo(): ?PDO
{
    $coreEnvPath = dirname(PROJECT_PATH, 2) . '/env/env.production.php';

    if (!is_file($coreEnvPath)) {
        return null;
    }

    $coreEnv = require $coreEnvPath;
    $coreDb = $coreEnv['db'] ?? [];

    try {
        return new PDO(
            "mysql:host={$coreDb['host']};dbname={$coreDb['name']};charset={$coreDb['charset']}",
            $coreDb['user'],
            $coreDb['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
            ]
        );
    } catch (Throwable $e) {
        return null;
    }
}

function financeCorePixSettings(): array
{
    $corePdo = financeCorePdo();

    if (!$corePdo) {
        return [];
    }

    try {
        return $corePdo->query("
            SELECT setting_key, setting_value
            FROM core_settings
            WHERE setting_key IN ('upgrade_pix_key', 'upgrade_pix_holder', 'upgrade_pix_notes')
        ")->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function financeWalletStatusLabel(string $status): string
{
    return match ($status) {
        'approved' => 'Aprovada',
        'rejected' => 'Rejeitada',
        default => 'Pendente',
    };
}

function financeWalletStatusBadge(string $status): string
{
    return match ($status) {
        'approved' => 'c-badge--success',
        'rejected' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function financeSetting(PDO $pdo, string $key, string $default = ''): string
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM finance_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value !== false && $value !== null ? (string)$value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function financeSetSetting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare("
        INSERT INTO finance_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$key, $value]);
}

