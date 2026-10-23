<?php
declare(strict_types=1);

/** First administrator setup for a local Cripto project, authorized by the Core session. */
function cryptoLocalSetupContext(PDO $projectPdo, array $project): ?array
{
    $root = dirname(PROJECT_PATH, 2);
    $file = $root . '/env/env.production.php';
    $config = is_file($file) ? require $file : [];
    $environment = strtolower(trim((string)($config['app']['environment'] ?? getenv('CORE_ENV') ?: '')));
    $host = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $local = $environment !== '' ? in_array($environment, ['local','development','laboratory','lab'], true) : in_array($host, ['localhost','127.0.0.1','[::1]'], true);
    $coreUserId = (int)($_SESSION['core_user']['id'] ?? 0);
    if (!$local || $coreUserId <= 0 || !is_array($config['db'] ?? null)) return null;
    if ((int)$projectPdo->query("SELECT COUNT(*) FROM project_users WHERE role='ADMIN'")->fetchColumn() > 0) return null;
    $db = $config['db'];
    try {
        $core = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $stmt = $core->prepare("SELECT id FROM core_users WHERE id=? AND role IN ('ADMIN','SUPER_ADMIN') AND status=1");
        $stmt->execute([$coreUserId]);
        if (!$stmt->fetchColumn()) return null;
        $stmt = $core->prepare("SELECT p.owner_name, p.owner_email FROM projects p JOIN bases b ON b.id=p.base_id WHERE p.id=? AND p.slug=? AND p.status='active' AND b.slug='cripto'");
        $stmt->execute([(int)($project['id'] ?? 0), basename(PROJECT_PATH)]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$owner || !filter_var($owner['owner_email'], FILTER_VALIDATE_EMAIL)) return null;
        return $owner;
    } catch (Throwable $e) { return null; }
}

function cryptoLocalSetupPage(PDO $pdo, array $project, array $owner): void
{
    $error = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verify();
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['confirm_password'] ?? '');
        unset($_POST['password'], $_POST['confirm_password']);
        if (strlen($password) < 8 || strlen($password) > 1024 || $password !== $confirmation) {
            $error = 'Use uma senha com pelo menos 8 caracteres e confirme a mesma senha.';
        } else {
            $lock = 'crypto_setup_' . substr(hash('sha256', (string)$pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 40);
            $locked = false;
            try {
                $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)'); $stmt->execute([$lock]); $locked = (int)$stmt->fetchColumn() === 1;
                if (!$locked) throw new RuntimeException('Setup em andamento.');
                $pdo->beginTransaction();
                if ((int)$pdo->query("SELECT COUNT(*) FROM project_users WHERE role='ADMIN'")->fetchColumn() > 0) throw new RuntimeException('Administrador existente.');
                $stmt = $pdo->prepare("INSERT INTO project_users (name,email,password,role,status,must_change_password) VALUES (?,?,?,'ADMIN','active',0)");
                $stmt->execute([$owner['owner_name'] ?: 'Administrador', $owner['owner_email'], password_hash($password, PASSWORD_DEFAULT)]);
                $id = (int)$pdo->lastInsertId();
                $pdo->commit();
                session_regenerate_id(true);
                $_SESSION['project_user_id'] = $id;
                $_SESSION['crypto_authenticated_project'] = hash('sha256', PROJECT_PATH);
                header('Location: ' . PROJECT_URL . '/admin/crypto_binance/index.php', true, 303);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Nao foi possivel concluir o primeiro acesso. Confira se o administrador ja foi definido e tente entrar no projeto.';
            } finally {
                unset($password, $confirmation);
                if ($locked) { $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)'); $stmt->execute([$lock]); }
            }
        }
        unset($password, $confirmation);
    }
    $title = 'Primeiro acesso local';
    ob_start();
    ?>
    <div class="c-auth-layout"><div class="c-auth-card">
     <h1 class="c-auth-title"><?= htmlspecialchars((string)$project['name']) ?></h1>
     <p class="c-auth-subtitle">Defina a senha do administrador deste projeto local. Seu acesso de administrador no Core autorizou esta configuracao inicial.</p>
     <p><?= htmlspecialchars($owner['owner_email']) ?></p>
     <?php if ($error): ?><div class="c-alert c-alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
     <form method="post" class="c-auth-form">
      <?= csrf_field() ?>
      <div class="c-auth-input"><input type="password" name="password" placeholder="Nova senha (minimo 8 caracteres)" minlength="8" maxlength="1024" autocomplete="new-password" required></div>
      <div class="c-auth-input"><input type="password" name="confirm_password" placeholder="Confirme a senha" minlength="8" maxlength="1024" autocomplete="new-password" required></div>
      <button class="c-auth-btn c-btn-block">Criar acesso local</button>
     </form>
    </div></div>
    <?php
    $content=ob_get_clean(); require APP_PATH . '/views/layout_auth.php';
}
