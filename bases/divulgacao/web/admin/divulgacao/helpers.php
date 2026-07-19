<?php
declare(strict_types=1);

function divulgacaoRequireAdmin(): void
{
    requireProjectRole(['ADMIN']);
}

function divulgacaoEnsureSchema(PDO $pdo): void
{
    $paths = [];

    if (defined('PROJECT_PATH')) {
        $paths[] = PROJECT_PATH . '/modules/divulgacao/database/schema.sql';
    }

    $paths[] = dirname(__DIR__, 3) . '/database/schema.sql';

    foreach ($paths as $path) {
        if (is_file($path)) {
            $pdo->exec((string)file_get_contents($path));
            divulgacaoEnsurePageColumns($pdo);
            return;
        }
    }
}

function divulgacaoEnsurePageColumns(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM divulgacao_pages');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['Field']] = true;
    }

    if (!isset($columns['action_type'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN action_type ENUM('capture', 'redirect', 'whatsapp') NOT NULL DEFAULT 'capture' AFTER whatsapp");
    }

    if (!isset($columns['destination_url'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN destination_url VARCHAR(500) NULL AFTER action_type");
    }

    if (!isset($columns['success_message'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN success_message VARCHAR(180) NULL AFTER destination_url");
    }

    if (!isset($columns['offer_image'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN offer_image VARCHAR(255) NULL AFTER body");
    }

    if (!isset($columns['offer_image_2'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN offer_image_2 VARCHAR(255) NULL AFTER offer_image");
    }

    if (!isset($columns['form_language'])) {
        $pdo->exec("ALTER TABLE divulgacao_pages ADD COLUMN form_language VARCHAR(10) NOT NULL DEFAULT 'pt' AFTER theme");
    }
}

function divulgacaoSlug(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'pagina-' . substr(md5((string)microtime(true)), 0, 8);
}

function divulgacaoTemplates(): array
{
    return [
        'servico' => [
            'label' => 'Servico',
            'headline' => 'Apresente seu servico com clareza',
            'subtitle' => 'Uma pagina direta para explicar a oferta, captar interessados e iniciar conversas.',
            'body' => 'Use este espaco para explicar para quem e a oferta, qual problema resolve e qual o proximo passo.',
            'cta' => 'Quero saber mais',
        ],
        'infoproduto' => [
            'label' => 'Infoproduto',
            'headline' => 'Transforme conhecimento em uma oferta simples',
            'subtitle' => 'Capture interessados antes de abrir turma, lista de espera ou lancamento.',
            'body' => 'Destaque promessa, formato, beneficios e para quem este conteudo foi criado.',
            'cta' => 'Entrar na lista',
        ],
        'evento' => [
            'label' => 'Evento',
            'headline' => 'Divulgue seu evento e organize os interessados',
            'subtitle' => 'Uma pagina para apresentar data, proposta e captar contatos.',
            'body' => 'Inclua local, publico ideal, programacao resumida e orientacao para inscricao.',
            'cta' => 'Quero participar',
        ],
        'produto' => [
            'label' => 'Produto',
            'headline' => 'Mostre o valor do produto em poucos segundos',
            'subtitle' => 'Apresente beneficios, diferenciais e capture pessoas interessadas.',
            'body' => 'Explique o produto, principais vantagens e como o atendimento ira continuar.',
            'cta' => 'Tenho interesse',
        ],
        'lista_espera' => [
            'label' => 'Lista de espera',
            'headline' => 'Entre na lista e receba a novidade primeiro',
            'subtitle' => 'Ideal para validar demanda antes de abrir uma oferta completa.',
            'body' => 'Conte o que esta sendo preparado e por que vale a pena acompanhar.',
            'cta' => 'Entrar na lista',
        ],
    ];
}

function divulgacaoTemplate(string $key): array
{
    $templates = divulgacaoTemplates();
    return $templates[$key] ?? $templates['servico'];
}

function divulgacaoActionOptions(): array
{
    return [
        'capture' => 'Salvar lead e permanecer na pagina',
        'redirect' => 'Salvar lead e enviar para link externo',
        'whatsapp' => 'Salvar lead e abrir WhatsApp',
    ];
}

function divulgacaoActionType(string $value): string
{
    return array_key_exists($value, divulgacaoActionOptions()) ? $value : 'capture';
}

function divulgacaoFormLanguages(): array
{
    return [
        'pt' => 'Portugues',
        'en' => 'English',
    ];
}

function divulgacaoFormLanguage(string $value): string
{
    return array_key_exists($value, divulgacaoFormLanguages()) ? $value : 'pt';
}

function divulgacaoFormTexts(string $language): array
{
    return match (divulgacaoFormLanguage($language)) {
        'en' => [
            'title' => 'Learn more',
            'description' => 'Enter your details to continue.',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'success' => 'Received. We will contact you soon.',
        ],
        default => [
            'title' => 'Quero saber mais',
            'description' => 'Envie seus dados para continuar.',
            'name' => 'Nome',
            'email' => 'E-mail',
            'phone' => 'Telefone',
            'success' => 'Recebido. Em breve entraremos em contato.',
        ],
    };
}

function divulgacaoExternalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('#^https?://#i', $value) === 1 ? $value : '';
}

function divulgacaoUploadOfferImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return '';
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $type = (string)($file['type'] ?? '');
    if (!isset($allowed[$type])) {
        return '';
    }

    $folder = PUBLIC_PATH . '/storage/uploads/divulgacao';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $name = 'oferta-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$type];
    $relativePath = 'storage/uploads/divulgacao/' . $name;
    $destination = PUBLIC_PATH . '/' . $relativePath;

    return move_uploaded_file((string)$file['tmp_name'], $destination) ? $relativePath : '';
}

function divulgacaoStatusLabel(string $status): string
{
    return $status === 'published' ? 'Publicado' : 'Rascunho';
}

function divulgacaoLeadStatusLabel(string $status): string
{
    return [
        'novo' => 'Novo',
        'contatado' => 'Contatado',
        'convertido' => 'Convertido',
        'arquivado' => 'Arquivado',
    ][$status] ?? ucfirst($status);
}

function divulgacaoBadgeClass(string $status): string
{
    return match ($status) {
        'published', 'convertido' => 'c-badge--success',
        'contatado' => 'c-badge--info',
        'arquivado' => 'c-badge--neutral',
        default => 'c-badge--warning',
    };
}

function divulgacaoPublicUrl(string $slug): string
{
    return PROJECT_URL . '/divulgacao.php?slug=' . urlencode($slug);
}

function divulgacaoSummary(PDO $pdo): array
{
    $pages = $pdo->query("
        SELECT
            COUNT(*) AS total_pages,
            COUNT(CASE WHEN status = 'published' THEN 1 END) AS published_pages,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) AS draft_pages
        FROM divulgacao_pages
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $leads = $pdo->query("
        SELECT
            COUNT(*) AS total_leads,
            COUNT(CASE WHEN status = 'novo' THEN 1 END) AS new_leads,
            COUNT(CASE WHEN status = 'convertido' THEN 1 END) AS converted_leads
        FROM divulgacao_leads
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    return array_merge([
        'total_pages' => 0,
        'published_pages' => 0,
        'draft_pages' => 0,
        'total_leads' => 0,
        'new_leads' => 0,
        'converted_leads' => 0,
    ], $pages, $leads);
}
