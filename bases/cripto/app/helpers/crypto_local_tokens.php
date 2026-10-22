<?php
declare(strict_types=1);

/** Local cohost bridge; access token and password changes share one MySQL transaction. */
final class CryptoLocalAccessTokens
{
    public function __construct(private PDO $core, private int $projectId, private string $slug, private string $database)
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/D', $database)) throw new RuntimeException('Configuracao local invalida.');
    }

    private function access(#[SensitiveParameter] string $token, bool $lock = false): array
    {
        if ($token === '' || strlen($token) > 256) throw new InvalidArgumentException('Token invalido ou expirado.');
        $stmt = $this->core->prepare("SELECT t.id, t.email, p.owner_name FROM project_access_tokens t JOIN projects p ON p.id=t.project_id JOIN bases b ON b.id=p.base_id WHERE t.token=? AND t.project_id=? AND p.slug=? AND p.status='active' AND b.slug='cripto' AND t.email=p.owner_email AND t.used=0 AND t.expires_at>=NOW()" . ($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$token, $this->projectId, $this->slug]);
        $access = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$access) throw new InvalidArgumentException('Token invalido ou expirado.');
        return $access;
    }

    public function validate(#[SensitiveParameter] string $token): void
    {
        $this->access($token);
    }

    public function setPassword(#[SensitiveParameter] string $token, #[SensitiveParameter] string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 72) throw new InvalidArgumentException('Use uma senha entre 8 e 72 caracteres.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->core->beginTransaction();
        try {
            $access = $this->access($token, true);
            $users = '`' . $this->database . '`.project_users';
            $stmt = $this->core->prepare('SELECT id, role FROM '.$users.' WHERE email=? FOR UPDATE');
            $stmt->execute([$access['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                if ($user['role'] !== 'ADMIN') throw new InvalidArgumentException('Este acesso nao corresponde ao administrador do projeto.');
                $this->core->prepare('UPDATE '.$users.' SET password=?, must_change_password=0 WHERE id=?')->execute([$hash, $user['id']]);
            } else {
                if ((int)$this->core->query("SELECT COUNT(*) FROM ".$users." WHERE role='ADMIN'")->fetchColumn() > 0) throw new InvalidArgumentException('O administrador ja foi definido. Use o acesso existente.');
                $this->core->prepare("INSERT INTO ".$users." (name,email,password,role,status,must_change_password) VALUES (?,?,?,'ADMIN','active',0)")->execute([$access['owner_name'] ?: 'Administrador', $access['email'], $hash]);
            }
            $this->core->prepare('UPDATE project_access_tokens SET used=1 WHERE id=? AND used=0')->execute([$access['id']]);
            $this->core->commit();
        } catch (Throwable $e) {
            if ($this->core->inTransaction()) $this->core->rollBack();
            throw $e;
        }
    }
}

function cryptoLocalTokenBridge(array $project): ?CryptoLocalAccessTokens
{
    $file = dirname(PROJECT_PATH, 2) . '/env/env.production.php';
    $config = is_file($file) ? require $file : [];
    $environment = strtolower(trim((string)($config['app']['environment'] ?? getenv('CORE_ENV') ?: '')));
    $host = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    $local = $environment !== '' ? in_array($environment, ['local','development','laboratory','lab'], true) : in_array($host, ['localhost','127.0.0.1','[::1]'], true);
    if (!$local) return null;
    $db = $config['db'] ?? [];
    $projectDb = require APP_PATH . '/config/database.php';
    if (!isset($db['host'], $db['name'], $db['user'], $db['pass']) || $db['host'] !== $projectDb['host']) throw new RuntimeException('Core local indisponivel.');
    $core = new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    return new CryptoLocalAccessTokens($core, (int)($project['id'] ?? 0), basename(PROJECT_PATH), (string)$projectDb['name']);
}

function cryptoLocalTokenPage(CryptoLocalAccessTokens $bridge, array $project, #[SensitiveParameter] string $token): void
{
    try { $bridge->validate($token); }
    catch (InvalidArgumentException $e) { http_response_code(403); exit('Token invalido, expirado ou de outro projeto.'); }
    catch (Throwable $e) { http_response_code(503); exit('Nao foi possivel validar o acesso no Core local. Tente novamente.'); }
    $error = null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        csrf_verify();
        $password = (string)($_POST['password'] ?? '');
        $confirmation = (string)($_POST['confirm_password'] ?? '');
        unset($_POST['password'], $_POST['confirm_password']);
        if ($password !== $confirmation) $error = 'Confirme a mesma senha nos dois campos.';
        else {
            try {
                $bridge->setPassword($token, $password);
                unset($password, $confirmation, $_SESSION['project_user_id']);
                session_regenerate_id(true);
                header('Location: ' . PROJECT_URL . '/admin/login.php', true, 303);
                return;
            } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
            catch (Throwable $e) { $error = 'Nao foi possivel criar o acesso. Nenhuma alteracao foi concluida. Tente novamente.'; }
        }
        unset($password, $confirmation);
    }
    $title = 'Criar senha';
    ob_start();
    ?>
    <div class="c-auth-layout"><div class="c-auth-card">
     <h1 class="c-auth-title"><?= htmlspecialchars((string)$project['name']) ?></h1>
     <p class="c-auth-subtitle">Defina sua senha para acessar o painel administrativo.</p>
     <?php if ($error): ?><div class="c-alert c-alert--error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
     <form method="post" class="c-auth-form">
      <?= csrf_field() ?>
      <div class="c-auth-input"><input type="password" name="password" placeholder="Nova senha (minimo 8 caracteres)" minlength="8" maxlength="72" autocomplete="new-password" required></div>
      <div class="c-auth-input"><input type="password" name="confirm_password" placeholder="Confirme a senha" minlength="8" maxlength="72" autocomplete="new-password" required></div>
      <button class="c-auth-btn c-btn-block">Criar senha</button>
     </form>
    </div></div>
    <?php
    $content = ob_get_clean(); require APP_PATH . '/views/layout_auth.php';
}
