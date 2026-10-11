<?php
declare(strict_types=1);

function landingPageRequireAdmin(): void
{
    requireProjectRole(['ADMIN']);
}

function landingPageEnsureSchema(PDO $pdo): void
{
    $paths = [];

    if (defined('PROJECT_PATH')) {
        $paths[] = PROJECT_PATH . '/modules/landing_page/database/schema.sql';
    }

    $paths[] = dirname(__DIR__, 3) . '/database/schema.sql';

    foreach ($paths as $path) {
        if (is_file($path)) {
            $pdo->exec((string)file_get_contents($path));
            landingPageEnsurePageColumns($pdo);
            return;
        }
    }
}

function landingPageEnsurePageColumns(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM landing_page_pages');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $columns[(string)$column['Field']] = true;
    }

    if (!isset($columns['banners_json'])) {
        $pdo->exec('ALTER TABLE landing_page_pages ADD COLUMN banners_json LONGTEXT NULL AFTER offer_image_2');
    }

    if (!isset($columns['action_type'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN action_type ENUM('capture', 'redirect', 'whatsapp') NOT NULL DEFAULT 'capture' AFTER whatsapp");
    }

    if (!isset($columns['destination_url'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN destination_url VARCHAR(500) NULL AFTER action_type");
    }

    if (!isset($columns['success_message'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN success_message VARCHAR(180) NULL AFTER destination_url");
    }

    if (!isset($columns['offer_image'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN offer_image VARCHAR(255) NULL AFTER body");
    }

    if (!isset($columns['offer_image_2'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN offer_image_2 VARCHAR(255) NULL AFTER offer_image");
    }

    if (!isset($columns['form_language'])) {
        $pdo->exec("ALTER TABLE landing_page_pages ADD COLUMN form_language VARCHAR(10) NOT NULL DEFAULT 'pt' AFTER theme");
    }
}

function landingPageSlug(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'pagina-' . substr(md5((string)microtime(true)), 0, 8);
}

function landingPageTemplates(): array
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

function landingPageTemplate(string $key): array
{
    $templates = landingPageTemplates();
    return $templates[$key] ?? $templates['servico'];
}

function landingPageActionOptions(): array
{
    return [
        'capture' => 'Salvar lead e permanecer na pagina',
        'redirect' => 'Salvar lead e enviar para link externo',
        'whatsapp' => 'Salvar lead e abrir WhatsApp',
    ];
}

function landingPageActionType(string $value): string
{
    return array_key_exists($value, landingPageActionOptions()) ? $value : 'capture';
}

function landingPageFormLanguages(): array
{
    return [
        'pt' => 'Portugues',
        'en' => 'English',
    ];
}

function landingPageFormLanguage(string $value): string
{
    return array_key_exists($value, landingPageFormLanguages()) ? $value : 'pt';
}

function landingPageFormTexts(string $language): array
{
    return match (landingPageFormLanguage($language)) {
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

function landingPageExternalUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    return preg_match('#^https?://#i', $value) === 1 ? $value : '';
}

function landingPageBannerUrl(string $value): string
{
    $value = trim($value);
    return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) === 1 ? $value : '';
}

function landingPageBanners(array $page, bool $linkedOnly = false): array
{
    $stored = json_decode((string)($page['banners_json'] ?? ''), true);
    if (!is_array($stored)) {
        $stored = [['image' => (string)($page['offer_image_2'] ?? '')]];
    }
    $banners = [];
    for ($i = 0; $i < 4; $i++) {
        $item = is_array($stored[$i] ?? null) ? $stored[$i] : [];
        $url = landingPageBannerUrl((string)($item['url'] ?? ''));
        if ($linkedOnly && $url === '') continue;
        $banners[] = [
            'url' => $url,
            'title' => trim((string)($item['title'] ?? '')),
            'image' => trim((string)($item['image'] ?? '')),
        ];
    }
    return $banners;
}

function landingPageSaveBanners(array $current, array $post, array $files): string
{
    $banners = landingPageBanners($current);
    $submitted = is_array($post['banners'] ?? null) ? $post['banners'] : [];
    for ($i = 0; $i < 4; $i++) {
        $item = is_array($submitted[$i] ?? null) ? $submitted[$i] : [];
        $banners[$i]['url'] = landingPageBannerUrl((string)($item['url'] ?? ''));
        $banners[$i]['title'] = trim((string)($item['title'] ?? ''));
        if (!empty($item['remove_image'])) $banners[$i]['image'] = '';
        $file = $files['banner_image_'.$i] ?? null;
        $uploaded = is_array($file) ? landingPageUploadOfferImage($file) : '';
        if ($uploaded !== '') $banners[$i]['image'] = $uploaded;
    }
    return json_encode($banners, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function landingPageUploadOfferImage(array $file): string
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

    $folder = PUBLIC_PATH . '/storage/uploads/landing_page';
    if (!is_dir($folder)) {
        mkdir($folder, 0755, true);
    }

    $name = 'oferta-' . date('YmdHis') . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$type];
    $relativePath = 'storage/uploads/landing_page/' . $name;
    $destination = PUBLIC_PATH . '/' . $relativePath;

    return move_uploaded_file((string)$file['tmp_name'], $destination) ? $relativePath : '';
}

function landingPageStatusLabel(string $status): string
{
    return $status === 'published' ? 'Publicado' : 'Rascunho';
}

function landingPageLeadStatusLabel(string $status): string
{
    return [
        'novo' => 'Novo',
        'contatado' => 'Contatado',
        'convertido' => 'Convertido',
        'arquivado' => 'Arquivado',
    ][$status] ?? ucfirst($status);
}

function landingPageBadgeClass(string $status): string
{
    return match ($status) {
        'published', 'convertido' => 'c-badge--success',
        'contatado' => 'c-badge--info',
        'arquivado' => 'c-badge--neutral',
        default => 'c-badge--warning',
    };
}

function landingPagePublicUrl(string $slug): string
{
    return PROJECT_URL . '/landing_page.php?slug=' . urlencode($slug);
}

function landingPageSummary(PDO $pdo): array
{
    $pages = $pdo->query("
        SELECT
            COUNT(*) AS total_pages,
            COUNT(CASE WHEN status = 'published' THEN 1 END) AS published_pages,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) AS draft_pages
        FROM landing_page_pages
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $leads = $pdo->query("
        SELECT
            COUNT(*) AS total_leads,
            COUNT(CASE WHEN status = 'novo' THEN 1 END) AS new_leads,
            COUNT(CASE WHEN status = 'convertido' THEN 1 END) AS converted_leads
        FROM landing_page_leads
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
