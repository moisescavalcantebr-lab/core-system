<?php
declare(strict_types=1);

function contasChart(array $months, string $title, bool $forecast = false): void
{
    $max = max([1, ...array_column($months, 'payable'), ...array_column($months, 'receivable')]);
    $receiveLabel = $forecast ? 'A receber' : 'Recebido';
    $payLabel = $forecast ? 'A pagar' : 'Pago';
    ?>
    <section class="a-section"><h3><?= contasEscape($title) ?></h3>
        <div class="a-legend"><span class="a-receive"><?= $receiveLabel ?></span><span class="a-pay"><?= $payLabel ?></span></div>
        <div class="a-scroll"><div class="a-chart" <?= $forecast ? '' : 'data-recent' ?> role="img" aria-label="<?= contasEscape($title) ?>; valores detalhados na tabela abaixo.">
            <div class="a-plot" style="--columns:<?= count($months) ?>">
                <?php foreach ($months as $month => $totals): ?><div class="a-bars">
                    <?php foreach (['receivable' => ['receive',$receiveLabel], 'payable' => ['pay',$payLabel]] as $kind => [$class,$label]): ?><div class="a-bar a-<?= $class ?>" style="height:<?= number_format($totals[$kind] / $max * 100,3,'.','') ?>%" title="<?= contasEscape(date('m/Y',strtotime($month.'-01')) . ' / ' . $label . ': ' . contasMoney($totals[$kind])) ?>"></div><?php endforeach; ?>
                </div><?php endforeach; ?>
            </div>
            <div class="a-chart-labels" style="--columns:<?= count($months) ?>"><?php foreach ($months as $month => $totals): ?><span><?= date('m/y',strtotime($month.'-01')) ?></span><?php endforeach; ?></div>
        </div></div>
        <details class="a-details"><summary>Valores por mes</summary><div class="a-scroll"><table class="c-table"><thead><tr><th>Mes</th><th><?= $receiveLabel ?></th><th><?= $payLabel ?></th><th><?= $forecast ? 'Diferenca prevista' : 'Resultado das movimentacoes' ?></th></tr></thead><tbody>
            <?php foreach ($months as $month => $totals): ?><tr><td><?= date('m/Y',strtotime($month.'-01')) ?></td><td><?= contasMoney($totals['receivable']) ?></td><td><?= contasMoney($totals['payable']) ?></td><td><?= contasMoney($totals['receivable']-$totals['payable']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div></details>
    </section>
    <?php
}
