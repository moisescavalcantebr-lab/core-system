<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

requireAdmin();

$checks = [];

/*
|--------------------------------------------------------------------------
| BANCO
|--------------------------------------------------------------------------
*/

try {
    $pdo->query("SELECT 1");

    $checks[] = [
        'label' => 'Banco de dados',
        'status' => 'OK'
    ];

} catch(Throwable $e){

    $checks[] = [
        'label' => 'Banco de dados',
        'status' => 'ERRO'
    ];
}

/*
|--------------------------------------------------------------------------
| TABELAS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = DATABASE()
");

$totalTables = $stmt->fetchColumn();

$checks[] = [
    'label'=>'Tabelas encontradas',
    'status'=>$totalTables
];

/*
|--------------------------------------------------------------------------
| ENGINE
|--------------------------------------------------------------------------
*/

$stmt=$pdo->query("
SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND engine!='InnoDB'
");

$badEngine=$stmt->fetchColumn();

$checks[]=[
'label'=>'Tabelas InnoDB',
'status'=>$badEngine==0?'OK':'Erro'
];

/*
|--------------------------------------------------------------------------
| CHARSET
|--------------------------------------------------------------------------
*/

$stmt=$pdo->query("
SELECT COUNT(*)
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_collation NOT LIKE 'utf8mb4%'
");

$badCharset=$stmt->fetchColumn();

$checks[]=[
'label'=>'Charset utf8mb4',
'status'=>$badCharset==0?'OK':'Erro'
];

/*
|--------------------------------------------------------------------------
| PASTAS
|--------------------------------------------------------------------------
*/

$folders=[
BASES_PATH=>'Bases',
PROJECTS_PATH=>'Projects',
ROOT_PATH.'/storage'=>'Storage'
];

foreach($folders as $path=>$label){

$checks[]=[
'label'=>$label,
'status'=>is_dir($path)?'OK':'Faltando'
];

}

/*
|--------------------------------------------------------------------------
| PHP
|--------------------------------------------------------------------------
*/

$checks[]=[
'label'=>'PHP',
'status'=>phpversion()
];

/*
|--------------------------------------------------------------------------
| CORE TABLES
|--------------------------------------------------------------------------
*/

$coreTables=[ /* mantido igual */ ];

$stmt=$pdo->query("SHOW TABLES");
$dbTables=$stmt->fetchAll(PDO::FETCH_COLUMN);

$tableStatus=[];

foreach($coreTables as $table){
$tableStatus[$table]=in_array($table,$dbTables);
}

/*
|--------------------------------------------------------------------------
| STATUS GERAL
|--------------------------------------------------------------------------
*/

$expectedTables=count($coreTables);
$installedTables=0;

foreach($tableStatus as $exists){
if($exists) $installedTables++;
}

$systemHealthy = (
$installedTables === $expectedTables &&
$badEngine == 0 &&
$badCharset == 0
);

$title = 'Sistema';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">
        <div>
            <h1 class="c-page-title">Verificação do Sistema</h1>
            <p class="c-page-subtitle">Status geral do ambiente</p>
        </div>
    </div>

    <div class="c-page-content">

    <div class="c-settings-grid">

        <!-- COLUNA 1 -->
        <div class="c-card">

            <h3>Status Geral</h3>

            <div class="c-table-wrapper">

                <table class="c-table">
                    <tbody>

                    <?php foreach($checks as $check): ?>

                        <?php
                        $status = $check['status'];

                        $class = match($status){
                            'OK' => 'c-badge--success',
                            'Erro','ERRO','Faltando' => 'c-badge--danger',
                            default => 'c-badge--neutral'
                        };
                        ?>

                        <tr>
                            <td><?= htmlspecialchars($check['label']) ?></td>
                            <td>
                                <?php if(in_array($status,['OK','Erro','ERRO','Faltando'])): ?>
                                    <span class="c-badge <?= $class ?>">
                                        <?= $status ?>
                                    </span>
                                <?php else: ?>
                                    <?= htmlspecialchars($status) ?>
                                <?php endif; ?>
                            </td>
                        </tr>

                    <?php endforeach ?>

                    </tbody>
                </table>

            </div>

        </div>

        <!-- COLUNA 2 -->
        <div class="c-card">

            <h3>Tabelas do Banco</h3>

<div class="c-table-wrapper">

    <table class="c-table">
        <tbody>

        <?php if (empty($dbTables)): ?>

        <tr>
            <td>Nenhuma tabela encontrada</td>
        </tr>

        <?php else: ?>

        <?php foreach($dbTables as $table): ?>

        <tr>
            <td><?= htmlspecialchars($table) ?></td>
        </tr>

        <?php endforeach ?>

        <?php endif; ?>

        </tbody>
    </table>

</div>					
					

        </div>

        <!-- COLUNA 3 -->
        <div class="c-card">

            <h3>Resumo</h3>

            <div class="c-dashboard-grid">

                <div class="c-dashboard-card <?= $systemHealthy ? 'c-card--success' : 'c-card--danger' ?>">
                    <h4>Sistema</h4>
                    <div class="c-metric"><?= $systemHealthy ? 'OK' : 'Problema' ?></div>
                </div>

                <div class="c-dashboard-card c-card--neutral">
                    <h4>Banco</h4>
                    <div class="c-metric">Conectado</div>
                </div>

                <div class="c-dashboard-card <?= $installedTables==$expectedTables ? 'c-card--success' : 'c-card--danger' ?>">
                    <h4>Tabelas</h4>
                    <div class="c-metric"><?= $installedTables ?>/<?= $expectedTables ?></div>
                </div>

                <div class="c-dashboard-card c-card--neutral">
                    <h4>Ambiente</h4>
                    <div class="c-metric">Produção</div>
                </div>

            </div>

        </div>

    </div>

</div>

</div>

<?php
$content = ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled = true;

$rightSidebarContent = '

<div class="c-card">

<h3>Informações</h3>

<p>
Verifica integridade do sistema, banco e estrutura.
</p>

</div>

';

require APP_PATH . '/views/layout_admin.php';