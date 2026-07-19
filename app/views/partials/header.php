<?php
$coreUser = $_SESSION['core_user'] ?? [];

if (!empty($coreUser['id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, avatar FROM core_users WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$coreUser['id']]);
        $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($freshUser) {
            $_SESSION['core_user'] = $freshUser;
            $coreUser = $freshUser;
        }
    } catch (Throwable $e) {
    }
}

$avatarFile = $coreUser['avatar'] ?? '';
$avatarPath = $avatarFile ? STORAGE_PATH . '/uploads/avatars/' . basename((string)$avatarFile) : '';
$avatarUrl = $avatarFile ? '/storage/uploads/avatars/' . rawurlencode(basename((string)$avatarFile)) : '';
$hasAvatar = $avatarFile && is_file($avatarPath);
$userName = $coreUser['name'] ?? 'Usuário';
?>

<header class="c-header-inner">

    <div class="c-header-left">

        <button class="c-sidebar-toggle" onclick="toggleSidebar()">☰</button>

        <?php if (!empty($coreSettings['app_logo'])): ?>

            <img src="/web/assets/uploads/<?= htmlspecialchars($coreSettings['app_logo']) ?>"
                 class="c-header-logo">

        <?php else: ?>

            <span class="c-header-title">
                <?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?>
            </span>

        <?php endif; ?>

    </div>

    <div class="c-header-right">

        <div class="c-header-user">

            <?php if ($hasAvatar): ?>

                <a href="/web/admin/settings/index.php#perfil" class="c-user-profile-link" title="Meu perfil">
                    <img src="<?= htmlspecialchars($avatarUrl) ?>?v=<?= filemtime($avatarPath) ?>"
                         class="c-user-avatar-img">
                </a>

            <?php else: ?>

                <a href="/web/admin/settings/index.php#perfil" class="c-user-name c-user-profile-link">
                    <?= htmlspecialchars($userName) ?>
                </a>

            <?php endif; ?>

            <a href="/web/admin/financeiro/saldo.php"
               class="c-header-action <?= str_contains($_SERVER['REQUEST_URI'] ?? '', '/web/admin/financeiro/saldo.php') ? 'is-active' : '' ?>">
                Carteira
            </a>

            <a href="/app/actions/auth/logout.php" class="c-header-action c-header-action--logout">
                Sair
            </a>

        </div>

    </div>

</header>
