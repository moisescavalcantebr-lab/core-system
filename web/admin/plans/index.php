<?php
require __DIR__ . '/../../../app/bootstrap/bootstrap.php';
require APP_PATH . '/helpers/auth.php';

requireAdmin();

/*
|--------------------------------------------------------------------------
| CRIAR PLANO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['name'] ?? '');
    $cycle = $_POST['billing_cycle'] ?? 'monthly';
    $price = (float)($_POST['price'] ?? 0);

    if ($name) {

        $stmt = $pdo->prepare("
            INSERT INTO plans (name,billing_cycle,price,status)
            VALUES (:name,:cycle,:price,1)
        ");

        $stmt->execute([
            'name'=>$name,
            'cycle'=>$cycle,
            'price'=>$price
        ]);
    }

    redirect('/public/admin/plans/index.php');
}

/*
|--------------------------------------------------------------------------
| PAGINAÇÃO
|--------------------------------------------------------------------------
*/

$order = $_GET['order'] ?? 'id';
$dir   = $_GET['dir'] ?? 'DESC';

$allowed=['id','name','billing_cycle','price','status'];

if(!in_array($order,$allowed)) $order='id';

$dir = strtoupper($dir)==='ASC'?'ASC':'DESC';

$page=max(1,(int)($_GET['page'] ?? 1));
$limit=10;
$offset=($page-1)*$limit;

$total=$pdo->query("SELECT COUNT(*) FROM plans")->fetchColumn();
$totalPages=max(1,ceil($total/$limit));

$stmt=$pdo->query("
SELECT * FROM plans
ORDER BY $order $dir
LIMIT $limit OFFSET $offset
");

$plans=$stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

$title='Planos';

ob_start();
?>

<div class="c-page">

    <div class="c-page-header">

        <div>
            <h1 class="c-page-title">Planos</h1>
            <p class="c-page-subtitle">Gerenciamento de planos</p>
        </div>

    </div>

    <div class="c-page-content">

        <?php if(empty($plans)): ?>

            <div class="c-card">
                Nenhum plano cadastrado.
            </div>

        <?php else: ?>

            <div class="c-table-wrapper">

                <table class="c-table">

                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th style="text-align:right;">Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach($plans as $plan): ?>

                        <?php $isFree = $plan['billing_cycle'] === 'free'; ?>

                        <tr>

                            <td>
                                <strong><?= htmlspecialchars($plan['name']) ?></strong>
                            </td>

                            <td>
                                <?= match($plan['billing_cycle']){
                                    'monthly' => 'Mensal',
                                    'annual'  => 'Anual',
                                    'free'    => 'Lifetime',
                                    default   => '-'
                                }; ?>
                            </td>

                            <td>
                                R$ <?= number_format($plan['price'],2,',','.') ?>
                            </td>

                            <td>

                                <?php if(!$isFree): ?>

                                    <span class="c-badge c-badge--<?= $plan['status'] ? 'success' : 'danger' ?> toggle-plan"
                                          data-id="<?= $plan['id'] ?>">
                                        <?= $plan['status'] ? 'Ativo' : 'Inativo' ?>
                                    </span>

                                <?php else: ?>

                                    <span class="c-badge c-badge--success">
                                        Ativo
                                    </span>

                                <?php endif; ?>

                            </td>

                            <td style="text-align:right;">

                                <?php if(!$isFree): ?>

                                    <a class="c-btn-secondary btn-sm"
                                       href="/public/admin/plans/edit.php?id=<?= $plan['id'] ?>">
                                        Editar
                                    </a>

                                    <a class="c-btn-secondary btn-sm"
                                       href="/app/actions/plans/delete.php?id=<?= $plan['id'] ?>"
                                       onclick="return confirm('Excluir plano?')">
                                        Excluir
                                    </a>

                                <?php else: ?>

                                    <span class="c-badge c-badge--warning">
                                        Protegido
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php endif; ?>

    </div>

</div>

<script>
document.querySelectorAll('.toggle-plan').forEach(el => {

    el.addEventListener('click', function(){

        fetch('/app/actions/plans/toggle.php', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body:JSON.stringify({id:this.dataset.id})
        })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){
                this.textContent = data.label;
                this.className = 'c-badge c-badge--' + data.class;
            }
        });

    });

});
</script>

<?php
$content=ob_get_clean();

/*
|--------------------------------------------------------------------------
| SIDEBAR
|--------------------------------------------------------------------------
*/

$rightSidebarEnabled=true;

$rightSidebarContent='

<div class="c-card">

<h3>Criar Plano</h3>

<form method="post">

<input class="c-input" name="name" placeholder="Nome do Plano" required>

<select class="c-input" name="billing_cycle">
<option value="monthly">Mensal</option>
<option value="annual">Anual</option>
</select>

<input class="c-input" type="number" step="0.01" name="price" placeholder="Valor">

<br><br>

<button class="c-btn-secondary c-btn-block">
Criar Plano
</button>

</form>

</div>

<br>

<div class="c-card">

<h3>Resumo</h3>

<p>Total: '.$total.'</p>

</div>
';

require APP_PATH.'/views/layout_admin.php';