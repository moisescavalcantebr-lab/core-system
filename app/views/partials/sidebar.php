<?php $current = $_SERVER['REQUEST_URI']; ?>

<nav class="c-sidebar-nav">

    <?php
    $menu = [

        'Principal' => [
            ['label' => 'Dashboard', 'url' => '/public/admin/dashboard.php', 'key' => 'dashboard'],
        ],

        'Gestão' => [
            ['label' => 'Bases', 'url' => '/public/admin/bases/index.php', 'key' => 'bases'],
            ['label' => 'Projetos', 'url' => '/public/admin/projects/index.php', 'key' => 'projects'],
            ['label' => 'Planos', 'url' => '/public/admin/plans/index.php', 'key' => 'plans'],
        ],

        'Conteúdo' => [
            ['label' => 'Páginas', 'url' => '/public/admin/pages/index.php', 'key' => 'pages'],
            ['label' => 'Biblioteca', 'url' => '/public/admin/media/index.php', 'key' => 'media'],
            ['label' => 'Leads', 'url' => '/public/admin/leads/index.php', 'key' => 'leads'],
        ],

        'Sistema' => [
            ['label' => 'Usuários', 'url' => '/public/admin/users/index.php', 'key' => 'users'],
            ['label' => 'Configurações', 'url' => '/public/admin/settings/index.php', 'key' => 'settings'],
            ['label' => 'System Check', 'url' => '/public/admin/system/index.php', 'key' => 'system'],
            ['label' => 'UI Registry', 'url' => '/public/admin/ui_registry/index.php', 'key' => 'ui_registry'],
            ['label' => 'Logs', 'url' => '/public/admin/logs/index.php', 'key' => 'logs'],
        ],

        'Conta' => [
            ['label' => 'Meu Perfil', 'url' => '/public/admin/profile/index.php', 'key' => 'profile'],
        ],
    ];
    ?>

    <?php foreach ($menu as $section => $items): ?>

        <div class="c-sidebar-section">

            <div class="c-sidebar-title"><?= $section ?></div>

            <?php foreach ($items as $item): ?>

                <a 
                    href="<?= $item['url'] ?>"
                    class="c-sidebar-link <?= isActive($item['key']) ?>"
                >
                    <?= $item['label'] ?>
                </a>

            <?php endforeach; ?>

        </div>

    <?php endforeach; ?>

</nav>