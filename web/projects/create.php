<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap/bootstrap.php';
require APP_PATH . '/services/projects/ProjectProvisioner.php';

$leadId = (int)($_GET['lead_id'] ?? $_POST['lead_id'] ?? 0);

if (!$leadId) {
    http_response_code(404);
    die('Lead não encontrado.');
}

$stmt = $pdo->prepare("SELECT * FROM leads WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $leadId]);
$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    http_response_code(404);
    die('Lead não encontrado.');
}

$error = null;
$resume = ($_GET['resume'] ?? '') === '1';
$siteName = trim($_POST['site_name'] ?? ($lead['site_name'] ?? ''));
$slug = trim($_POST['slug'] ?? ($lead['slug'] ?? ''));
$baseId = (int)($lead['base_id'] ?? 0);

$baseName = null;

if ($baseId <= 0) {
    $stmt = $pdo->query("
        SELECT id, name
        FROM bases
        WHERE status = 1
        AND slug != 'base'
        ORDER BY id ASC
        LIMIT 2
    ");
    $availableBases = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($availableBases) === 1) {
        $baseId = (int)$availableBases[0]['id'];
        $baseName = (string)$availableBases[0]['name'];
    }
}

if ($baseId > 0) {
    $stmt = $pdo->prepare("
        SELECT name
        FROM bases
        WHERE id = :id
        AND status = 1
        AND slug != 'base'
        LIMIT 1
    ");
    $stmt->execute(['id' => $baseId]);
    $baseName = $stmt->fetchColumn() ?: null;
}

function normalizeProjectSlug(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
    return trim((string)$slug, '-');
}

function uniqueProjectSlug(PDO $pdo, string $baseSlug, int $leadId): string
{
    $candidate = $baseSlug;
    $suffix = 1;

    while (true) {
        $stmt = $pdo->prepare("
            SELECT 1
            FROM projects
            WHERE slug = :slug
            LIMIT 1
        ");
        $stmt->execute(['slug' => $candidate]);
        $projectExists = (bool)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT 1
            FROM leads
            WHERE slug = :slug
            AND id != :lead_id
            AND implementation_status IN ('ready', 'converted')
            LIMIT 1
        ");
        $stmt->execute([
            'slug' => $candidate,
            'lead_id' => $leadId,
        ]);
        $leadExists = (bool)$stmt->fetchColumn();

        if (!$projectExists && !$leadExists) {
            return $candidate;
        }

        $candidate = $baseSlug . '-' . str_pad((string)$suffix, 2, '0', STR_PAD_LEFT);
        $suffix++;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = ProjectProvisioner::normalizeSlug($slug);

    if ($siteName === '' || $slug === '') {
        $error = 'Preencha todos os campos.';
    } elseif (!$baseId || !$baseName) {
        $error = 'A base deste projeto não está configurada. Entre em contato com o suporte.';
    } elseif (!preg_match('/^[a-z0-9-]{3,60}$/', $slug)) {
        $error = 'Use um slug com letras minúsculas, números e hífen.';
    } else {
        $slug = uniqueProjectSlug($pdo, $slug, $leadId);

        try {
            $stmt = $pdo->prepare("
                UPDATE leads
                SET site_name = :site_name,
                    slug = :slug,
                    base_id = :base_id,
                    implementation_status = 'creating',
                    status = 'qualified'
                WHERE id = :id
            ");

            $stmt->execute([
                'site_name' => $siteName,
                'slug' => $slug,
                'base_id' => $baseId,
                'id' => $leadId,
            ]);

            $project = ProjectProvisioner::createFreeProject($pdo, [
                'name' => $siteName,
                'slug' => $slug,
                'owner_name' => (string)($lead['name'] ?? ''),
                'owner_email' => (string)($lead['email'] ?? ''),
                'base_id' => $baseId,
                'lead_id' => $leadId,
                'meta' => [
                    'ip_address' => $lead['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                    'user_agent' => $lead['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                ],
            ]);

            $mailId = ProjectProvisioner::sendAccessEmail($pdo, (int)$project['project_id']);
            $status = $mailId ? 'created' : 'created_email_pending';

            header('Location: /web/projects/thanks.php?status=' . $status . '&project=' . urlencode((string)$project['slug']));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$title = 'Configurar Projeto';

$extraCss = '
<link rel="stylesheet" href="/web/assets/css/core_page.css">
';

ob_start();
?>

<main class="c-page project-request-page">
    <section class="block block-lead">
        <div class="c-container text-center">
            <h1 class="block-title">Quase lá</h1>
            <p class="block-description">
                Agora conte como será o projeto para prepararmos a criação no painel.
            </p>

            <?php if ($error): ?>
                <p class="form-success" style="color: var(--color-danger);">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <?php if ($resume && !$error): ?>
                <p class="form-success">
                    Encontramos sua solicitação. Continue preenchendo os dados para finalizar.
                </p>
            <?php endif; ?>

            <form method="post" class="lead-form-inner">
                <input type="hidden" name="lead_id" value="<?= $leadId ?>">

                <input
                    type="text"
                    name="site_name"
                    placeholder="Nome do projeto/site"
                    value="<?= htmlspecialchars($siteName) ?>"
                    maxlength="150"
                    required>

                <input
                    type="text"
                    name="slug"
                    placeholder="Slug para URL. Ex: minha-loja"
                    value="<?= htmlspecialchars($slug) ?>"
                    maxlength="60"
                    required>
                <button class="btn btn-primary" type="submit">
                    Enviar solicitação
                </button>
            </form>
        </div>
    </section>
</main>

<?php
$content = ob_get_clean();
require APP_PATH . '/views/layout_base.php';
