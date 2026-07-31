<?php
$current = $_SERVER['REQUEST_URI'];

function coreSidebarIcon(string $key): string
{
    $icons = [
        'dashboard' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h5v-6h4v6h5v-9.5"/>',
        'bases' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'modules' => '<path d="m12 3 8 4.5-8 4.5-8-4.5Z"/><path d="m4 12 8 4.5 8-4.5"/><path d="m4 16.5 8 4.5 8-4.5"/>',
        'projects' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 5V3h8v2"/><path d="M3 11h18"/>',
        'plans' => '<path d="M20 12 12 20 4 12l8-8Z"/><path d="M12 4v16"/>',
        'bases/vitrines' => '<path d="M4 7h16l-1 12H5Z"/><path d="M8 7a4 4 0 0 1 8 0"/>',
        'financeiro' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/>',
        'pages' => '<path d="M6 3h8l4 4v14H6Z"/><path d="M14 3v5h5"/><path d="M8 13h8"/><path d="M8 17h5"/>',
        'media' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 13l2.5-2.5L14 14l2-2 3 3"/><circle cx="8" cy="9" r="1"/>',
        'leads' => '<path d="M4 6h16v12H4Z"/><path d="m4 7 8 6 8-6"/>',
        'content_studio' => '<path d="M4 5h16v14H4Z"/><path d="M8 9h8"/><path d="M8 13h5"/><path d="M17 13l2 2-4 4h-2v-2Z"/>',
        'quick_links' => '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2"/><circle cx="9.5" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'logs' => '<path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/>',
        'system' => '<rect x="4" y="5" width="16" height="11" rx="2"/><path d="M8 21h8"/><path d="M12 16v5"/><path d="m9 10 2 2 4-4"/>',
        'settings' => '<path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/><path d="M19.4 15a1.8 1.8 0 0 0 .4 2l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.8 1.8 0 0 0-2-.4 1.8 1.8 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.8 1.8 0 0 0-1-1.6 1.8 1.8 0 0 0-2 .4l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.8 1.8 0 0 0 .4-2 1.8 1.8 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.8 1.8 0 0 0 1.6-1 1.8 1.8 0 0 0-.4-2l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.8 1.8 0 0 0 2 .4 1.8 1.8 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.8 1.8 0 0 0 1 1.6 1.8 1.8 0 0 0 2-.4l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.8 1.8 0 0 0-.4 2 1.8 1.8 0 0 0 1.6 1H21a2 2 0 1 1 0 4h-.1a1.8 1.8 0 0 0-1.5 1Z"/>',
    ];

    $paths = $icons[$key] ?? '<circle cx="12" cy="12" r="8"/>';

    return '<span class="c-sidebar-link-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg></span>';
}

$menu = [
    'Principal' => [
        ['label' => 'Dashboard', 'url' => '/web/admin/dashboard.php', 'key' => 'dashboard'],
    ],

    'Gestão' => [
        ['label' => 'Bases', 'url' => '/web/admin/bases/index.php', 'key' => 'bases'],
        ['label' => 'Módulos', 'url' => '/web/admin/modules/index.php', 'key' => 'modules'],
        ['label' => 'Projetos', 'url' => '/web/admin/projects/index.php', 'key' => 'projects'],
        ['label' => 'Planos', 'url' => '/web/admin/plans/index.php', 'key' => 'plans'],
        ['label' => 'Vitrine', 'url' => '/web/admin/bases/vitrines.php', 'key' => 'bases/vitrines'],
        ['label' => 'Financeiro', 'url' => '/web/admin/financeiro/index.php', 'key' => 'financeiro'],
    ],

    'Conteúdo' => [
        ['label' => 'Páginas', 'url' => '/web/admin/pages/index.php', 'key' => 'pages'],
        ['label' => 'Categorias Blog', 'url' => '/web/admin/pages/taxonomy.php', 'key' => 'pages'],
        ['label' => 'Biblioteca', 'url' => '/web/admin/media/index.php', 'key' => 'media'],
        ['label' => 'Leads', 'url' => '/web/admin/leads/index.php', 'key' => 'leads'],
        ['label' => 'Content Studio', 'url' => '/web/admin/content_studio/index.php', 'key' => 'content_studio'],
    ],

    'Sistema' => [
        ['label' => 'Acessos Rápidos', 'url' => '/web/admin/quick_links/index.php', 'key' => 'quick_links'],
        ['label' => 'Usuários', 'url' => '/web/admin/users/index.php', 'key' => 'users'],
        ['label' => 'Logs', 'url' => '/web/admin/logs/index.php', 'key' => 'logs'],
        ['label' => 'System Check', 'url' => '/web/admin/system/index.php', 'key' => 'system'],
        ['label' => 'Configurações', 'url' => '/web/admin/settings/index.php', 'key' => 'settings'],
    ],
];
?>

<nav class="c-sidebar-nav">
    <?php foreach ($menu as $section => $items): ?>
        <div class="c-sidebar-section">
            <div class="c-sidebar-title"><?= $section ?></div>

            <?php foreach ($items as $item): ?>
                <a
                    href="<?= $item['url'] ?>"
                    class="c-sidebar-link <?= isActive($item['key']) ?>"
                >
                    <?= coreSidebarIcon($item['key']) ?>
                    <span><?= $item['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</nav>
