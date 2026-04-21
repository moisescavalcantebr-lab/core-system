<header class="c-header-inner">

    <div class="c-header-left">

        <button class="c-sidebar-toggle" onclick="toggleSidebar()">☰</button>

        <?php if (!empty($coreSettings['app_logo'])): ?>

            <img src="/storage/uploads/logos/<?= htmlspecialchars($coreSettings['app_logo']) ?>"
                 class="c-header-logo">

        <?php else: ?>

            <span class="c-header-title">
                <?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?>
            </span>

        <?php endif; ?>

    </div>

    <div class="c-header-right">

        <div class="c-header-user">

            <?php if (!empty($_SESSION['core_user']['avatar'])): ?>

                <img src="/storage/uploads/avatars/<?= htmlspecialchars($_SESSION['core_user']['avatar']) ?>"
                     class="c-user-avatar-img">

            <?php else: ?>

                <div class="c-user-avatar">
                    <?= strtoupper(substr($_SESSION['core_user']['name'], 0, 1)) ?>
                </div>

            <?php endif; ?>

            <span class="c-user-name">
                <?= htmlspecialchars($_SESSION['core_user']['name']) ?>
            </span>

            <a href="/app/actions/auth/logout.php" class="c-user-logout">
                Sair
            </a>

        </div>

    </div>

</header>