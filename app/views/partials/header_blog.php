<?php

/* =========================
BUSCAR CATEGORIAS
========================= */

/*
 * Como ainda não temos tabela de categorias,
 * vamos usar o próprio model_slug como base
 * (ex: model_blog, etc)
 * Depois podemos evoluir isso.
 */

$stmt = $pdo->query("
    SELECT DISTINCT category
    FROM core_page_contents
    WHERE type='blog'
    AND status='published'
    AND model_slug IS NOT NULL
");

$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>

<header class="public-header">

    <div class="header-inner">

        <!-- LOGO -->
    <div class="header-left">
<?php if (!empty($coreSettings['app_logo'])): ?>

<img src="/public/assets/uploads/<?= htmlspecialchars($coreSettings['app_logo']) ?>" 
     alt="Logo"
     style="height:60px;width:auto;">

<?php else: ?>

<strong><?= htmlspecialchars($coreSettings['app_name'] ?? 'CORE') ?></strong>

<?php endif; ?> 
	</div>

        <!-- MENU -->
      		
		<nav class="header-nav">

<a href="#.php"class="nav-item">Início</a>

</nav>
		
		
		
		
		
		
		
    </div>

</header>