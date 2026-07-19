<?php
declare(strict_types=1);

final class ContentStudioService
{
    public static function ensureSchema(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_channels (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                platform VARCHAR(60) NOT NULL,
                handle VARCHAR(120) NULL,
                url VARCHAR(500) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_status (status),
                KEY idx_platform (platform)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_niches (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                description TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (slug),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_personas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                slug VARCHAR(140) NOT NULL,
                description TEXT NULL,
                voice_notes TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (slug),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_campaigns (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(160) NOT NULL,
                slug VARCHAR(180) NOT NULL,
                campaign_key VARCHAR(80) NOT NULL,
                objective VARCHAR(120) NULL,
                project_id INT UNSIGNED NULL,
                page_id INT UNSIGNED NULL,
                landing_url VARCHAR(500) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                starts_at DATE NULL,
                ends_at DATE NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_slug (slug),
                UNIQUE KEY uq_campaign_key (campaign_key),
                KEY idx_status (status),
                KEY idx_project (project_id),
                KEY idx_page (page_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_ideas (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT UNSIGNED NULL,
                niche_id INT UNSIGNED NULL,
                persona_id INT UNSIGNED NULL,
                title VARCHAR(180) NOT NULL,
                hook VARCHAR(255) NULL,
                format VARCHAR(60) NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(30) NOT NULL DEFAULT 'idea',
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_campaign (campaign_id),
                KEY idx_status (status),
                KEY idx_priority (priority)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_scripts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idea_id INT UNSIGNED NULL,
                title VARCHAR(180) NOT NULL,
                format VARCHAR(60) NULL,
                script_body MEDIUMTEXT NULL,
                cta TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'draft',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_idea (idea_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_prompts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                idea_id INT UNSIGNED NULL,
                script_id INT UNSIGNED NULL,
                title VARCHAR(180) NOT NULL,
                prompt_text MEDIUMTEXT NOT NULL,
                context VARCHAR(120) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_idea (idea_id),
                KEY idx_script (script_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_publications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT UNSIGNED NULL,
                idea_id INT UNSIGNED NULL,
                channel_id INT UNSIGNED NULL,
                title VARCHAR(180) NOT NULL,
                destination_url VARCHAR(500) NULL,
                scheduled_at DATETIME NULL,
                published_at DATETIME NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'planned',
                external_id VARCHAR(180) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_campaign (campaign_id),
                KEY idx_channel (channel_id),
                KEY idx_status (status),
                KEY idx_scheduled (scheduled_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_assets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                media_id INT UNSIGNED NOT NULL,
                campaign_id INT UNSIGNED NULL,
                idea_id INT UNSIGNED NULL,
                publication_id INT UNSIGNED NULL,
                usage_type VARCHAR(60) NOT NULL DEFAULT 'reference',
                title VARCHAR(180) NULL,
                notes TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_media (media_id),
                KEY idx_campaign (campaign_id),
                KEY idx_idea (idea_id),
                KEY idx_publication (publication_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS content_studio_metrics (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT UNSIGNED NULL,
                publication_id INT UNSIGNED NULL,
                impressions INT UNSIGNED NOT NULL DEFAULT 0,
                clicks INT UNSIGNED NOT NULL DEFAULT 0,
                leads INT UNSIGNED NOT NULL DEFAULT 0,
                conversions INT UNSIGNED NOT NULL DEFAULT 0,
                spend DECIMAL(12,2) NOT NULL DEFAULT 0,
                revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
                captured_at DATE NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_campaign (campaign_id),
                KEY idx_publication (publication_id),
                KEY idx_captured (captured_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    public static function slug(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return $value !== '' ? $value : 'item-' . bin2hex(random_bytes(3));
    }

    public static function dashboard(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return [
            'campaigns_active' => self::countWhere($pdo, 'content_studio_campaigns', "status='active'"),
            'campaigns_draft' => self::countWhere($pdo, 'content_studio_campaigns', "status='draft'"),
            'ideas_open' => self::countWhere($pdo, 'content_studio_ideas', "status IN ('idea','draft','review')"),
            'publications_planned' => self::countWhere($pdo, 'content_studio_publications', "status='planned'"),
            'publications_published' => self::countWhere($pdo, 'content_studio_publications', "status='published'"),
            'publications_late' => self::countWhere($pdo, 'content_studio_publications', "status='planned' AND scheduled_at IS NOT NULL AND scheduled_at < NOW()"),
            'channels_active' => self::countWhere($pdo, 'content_studio_channels', "status='active'"),
            'scripts_draft' => self::countWhere($pdo, 'content_studio_scripts', "status IN ('draft','review')"),
            'prompts_active' => self::countWhere($pdo, 'content_studio_prompts', "status='active'"),
            'assets_active' => self::countWhere($pdo, 'content_studio_assets', "status='active'"),
            'leads_total' => self::safeCount($pdo, 'leads'),
            'leads_content_studio' => self::safeScalar($pdo, "SELECT COUNT(*) FROM leads WHERE COALESCE(content_campaign_key, '') <> '' OR COALESCE(content_source, '') <> ''"),
        ];
    }

    public static function countWhere(PDO $pdo, string $table, string $where): int
    {
        return (int)$pdo->query("SELECT COUNT(*) FROM {$table} WHERE {$where}")->fetchColumn();
    }

    public static function safeCount(PDO $pdo, string $table): int
    {
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function safeScalar(PDO $pdo, string $query): int
    {
        try {
            return (int)$pdo->query($query)->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function listCampaigns(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT c.*,
                   p.title AS page_title,
                   p.slug AS page_slug,
                   pr.name AS project_name,
                   (
                       SELECT COUNT(*)
                       FROM leads l
                       WHERE CONVERT(l.content_campaign_key USING utf8mb4) COLLATE utf8mb4_unicode_ci
                           = CONVERT(c.campaign_key USING utf8mb4) COLLATE utf8mb4_unicode_ci
                   ) AS leads_count
            FROM content_studio_campaigns c
            LEFT JOIN core_page_contents p ON p.id = c.page_id
            LEFT JOIN projects pr ON pr.id = c.project_id
            ORDER BY c.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listIdeas(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT i.*, c.name AS campaign_name, n.name AS niche_name, pe.name AS persona_name
            FROM content_studio_ideas i
            LEFT JOIN content_studio_campaigns c ON c.id = i.campaign_id
            LEFT JOIN content_studio_niches n ON n.id = i.niche_id
            LEFT JOIN content_studio_personas pe ON pe.id = i.persona_id
            ORDER BY i.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listScripts(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT s.*, i.title AS idea_title, c.name AS campaign_name
            FROM content_studio_scripts s
            LEFT JOIN content_studio_ideas i ON i.id = s.idea_id
            LEFT JOIN content_studio_campaigns c ON c.id = i.campaign_id
            ORDER BY s.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listPrompts(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT p.*, i.title AS idea_title, s.title AS script_title
            FROM content_studio_prompts p
            LEFT JOIN content_studio_ideas i ON i.id = p.idea_id
            LEFT JOIN content_studio_scripts s ON s.id = p.script_id
            ORDER BY p.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listPublications(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT p.*, c.name AS campaign_name, c.campaign_key, ch.name AS channel_name, i.title AS idea_title
            FROM content_studio_publications p
            LEFT JOIN content_studio_campaigns c ON c.id = p.campaign_id
            LEFT JOIN content_studio_channels ch ON ch.id = p.channel_id
            LEFT JOIN content_studio_ideas i ON i.id = p.idea_id
            ORDER BY COALESCE(p.scheduled_at, p.created_at) DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function listAssets(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT a.*,
                   m.file_name,
                   c.name AS campaign_name,
                   i.title AS idea_title,
                   p.title AS publication_title
            FROM content_studio_assets a
            INNER JOIN core_media m ON m.id = a.media_id
            LEFT JOIN content_studio_campaigns c ON c.id = a.campaign_id
            LEFT JOIN content_studio_ideas i ON i.id = a.idea_id
            LEFT JOIN content_studio_publications p ON p.id = a.publication_id
            ORDER BY a.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function availableMedia(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT id, file_name, created_at
            FROM core_media
            ORDER BY id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activeCampaigns(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("SELECT id, name FROM content_studio_campaigns ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activeIdeas(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT id, title
            FROM content_studio_ideas
            WHERE status <> 'archived'
            ORDER BY title
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activeScripts(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT id, title
            FROM content_studio_scripts
            WHERE status <> 'archived'
            ORDER BY title
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function activePublications(PDO $pdo): array
    {
        self::ensureSchema($pdo);

        return $pdo->query("
            SELECT id, title
            FROM content_studio_publications
            WHERE status <> 'canceled'
            ORDER BY title
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function references(PDO $pdo, string $table, bool $activeOnly = false): array
    {
        self::ensureSchema($pdo);

        $where = $activeOnly ? " WHERE status = 'active'" : '';

        return $pdo->query("SELECT * FROM {$table}{$where} ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function campaignDestination(array $campaign): string
    {
        $landingUrl = trim((string)($campaign['landing_url'] ?? ''));
        if ($landingUrl !== '') {
            return $landingUrl;
        }

        $pageSlug = trim((string)($campaign['page_slug'] ?? ''));
        if ($pageSlug !== '') {
            return '/web/p.php?slug=' . rawurlencode($pageSlug);
        }

        return '';
    }

    public static function campaignTrackingUrl(array $campaign, string $source = ''): string
    {
        $destination = self::campaignDestination($campaign);
        if ($destination === '') {
            return '';
        }

        $params = [
            'cs_campaign' => (string)($campaign['campaign_key'] ?? ''),
        ];

        $source = trim($source);
        if ($source !== '') {
            $params['cs_source'] = self::slug($source);
        }

        $separator = str_contains($destination, '?') ? '&' : '?';

        return $destination . $separator . http_build_query($params);
    }
}
