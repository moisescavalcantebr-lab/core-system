<?php
declare(strict_types=1);

function eqCoverage(array $bucket, string $field): string
{
    return $bucket[$field.'_days'].'/'.$bucket['days'].' dias informados';
}

function eqReadingTable(array $data, string $listUrl, bool $archived): void
{
    $previousMonth = (new DateTimeImmutable($data['month'].'-01'))->modify('-1 month')->format('m/Y');
    ?>
    <div class="eq-scroll"><table class="c-table eq-readings eq-daily-table"><thead><tr><th>Data</th><th>Razao / Local</th><th>Total anterior<small><?= $previousMonth ?></small></th><th>Total</th><th>Executadas</th><th>Pendentes</th><th>Enviadas</th><th>LIS-S</th><th>LIS-N</th><th>Situacao</th><th>Acoes</th></tr></thead><tbody>
    <?php foreach(array_reverse($data['daily']) as $row):
        $previous = $data['previous_totals'][(int)$row['reason_id']] ?? null;
        $delta = $previous === null ? null : (int)$row['total'] - $previous;
    ?>
    <tr><td data-label="Data"><?= date('d/m/Y',strtotime($row['work_date'])) ?></td><td data-label="Razao / Local"><?= eqEscape($row['reason_number']) ?><small><?= eqEscape($row['location']) ?></small></td>
    <td data-label="Total anterior (<?= $previousMonth ?>)"><?= eqNumber($previous) ?></td><td data-label="Total"><?= eqNumber($row['total']) ?><?php if($delta !== null): ?><small><span class="<?= $delta < 0 ? 'eq-negative' : 'eq-positive' ?>"><?= $delta > 0 ? '+' : '' ?><?= eqNumber($delta) ?><?= $delta === 0 ? ' / sem variacao' : '' ?></span></small><?php endif; ?></td>
    <td data-label="Executadas"><?= eqNumber($row['executed']) ?></td><td data-label="Pendentes"><?= eqNumber($row['executed'] === null ? null : (int)$row['total']-(int)$row['executed']) ?></td><td data-label="Enviadas"><?= eqNumber($row['sent']) ?></td><td data-label="LIS-S"><?= eqNumber($row['lis']) ?></td><td data-label="LIS-N"><?= eqNumber($row['leit']) ?></td><td data-label="Situacao"><?= eqStatus($row) ?></td><td>
    <?php if($archived): ?><span class="eq-muted">Arquivado</span><?php else: ?><div class="eq-actions"><a class="c-btn-secondary" title="Editar leitura" aria-label="Editar leitura <?= eqEscape($row['work_date']) ?>" href="<?= eqEscape($listUrl.'&view=edit&id='.$row['id']) ?>">&#9998;</a><form method="post" data-eq-confirm="Excluir este lancamento diario?"><?= csrf_field() ?><input type="hidden" name="operation" value="delete_reading"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="revision" value="<?= (int)$row['revision'] ?>"><button class="c-btn-secondary" title="Excluir leitura" aria-label="Excluir leitura <?= eqEscape($row['work_date']) ?>">&#10005;</button></form></div><?php endif; ?>
    </td></tr><?php endforeach; ?>
    <?php if(!$data['daily']): ?><tr><td colspan="11">Nenhuma leitura cadastrada neste mes.</td></tr><?php endif; ?></tbody></table></div>
    <?php
}

function eqChart(array $buckets, string $title, array $fields, bool $daily = false): void
{
    $max = 1;
    foreach ($buckets as $row) foreach ($fields as $key => $label) $max = max($max, (int)($row[$key] ?? 0));
    ?>
    <section class="eq-section"><h3><?= eqEscape($title) ?></h3>
    <?php if (!$buckets || (!$daily && array_sum(array_column($buckets, 'days')) === 0)): ?><p class="eq-muted">Nenhum lancamento no periodo.</p><?php else: ?>
    <div class="eq-legend"><?php foreach ($fields as $key=>$label): ?><span><i class="eq-<?= $key ?>"></i><?= eqEscape($label) ?></span><?php endforeach; ?></div>
    <div class="eq-scroll"><div class="eq-chart" style="--eq-columns:<?= count($buckets) ?>;min-width:<?= max(650,count($buckets)*50) ?>px" role="img" aria-label="<?= eqEscape($title) ?>">
    <?php foreach ($buckets as $key=>$row): $label = $daily ? date('d/m',strtotime($row['work_date'])) : date('m/y',strtotime($key.'-01')); ?>
    <div class="eq-column"><div class="eq-bars"><?php foreach($fields as $field=>$fieldLabel): $value = $daily ? $row[$field] : (($row[$field.'_days'] ?? $row['days']) > 0 ? $row[$field] : null); ?><div class="eq-bar eq-<?= $field ?>" style="height:<?= number_format((int)$value/$max*100,3,'.','') ?>%" title="<?= eqEscape($label.' / '.$fieldLabel.': '.eqNumber($value)) ?>" aria-label="<?= eqEscape($label.' / '.$fieldLabel.': '.eqNumber($value)) ?>"></div><?php endforeach; ?></div><small><?= $label ?></small></div>
    <?php endforeach; ?></div></div>
    <?php endif; ?></section>
    <?php
}

function eqReport(array $data): void
{
    $current = $data['current'];
    ?>
    <div class="eq-metrics">
    <div><span>Dias registrados</span><strong><?= eqNumber($current['days']) ?></strong></div>
    <?php foreach(['total'=>'Total recebido','executed'=>'Executadas','sent'=>'Enviadas','pending'=>'Pendentes conhecidas','unsent'=>'Executadas ainda nao enviadas','lis'=>'LIS-S - com impressao','leit'=>'LIS-N - sem impressao'] as $field=>$label):
        $coverage = $field === 'pending' ? 'executed' : ($field === 'unsent' ? 'sent' : $field);
        $value = $field === 'total' ? $current[$field] : ($current[$coverage.'_days'] > 0 ? $current[$field] : null);
    ?><div><span><?= $label ?></span><strong><?= eqNumber($value) ?></strong><?php if($field !== 'total'): ?><small><?= eqEscape(eqCoverage($current,$coverage)) ?></small><?php endif; ?></div><?php endforeach; ?>
    </div>
    <section class="eq-section"><h3>Comparacao com o mes anterior</h3><p class="eq-muted"><?= eqNumber($current['days']) ?> dias registrados neste mes / <?= eqNumber($data['previous']['days']) ?> no anterior</p><div class="eq-comparison">
    <?php foreach(['lis'=>'LIS-S','leit'=>'LIS-N'] as $field=>$label): $delta=$data['deltas'][$field]; ?><div><strong><?= $label ?></strong><p>Atual: <?= eqNumber($current[$field.'_days'] ? $current[$field] : null) ?> / Anterior: <?= eqNumber($data['previous'][$field.'_days'] ? $data['previous'][$field] : null) ?></p>
    <?php if($delta['amount'] === null): ?><p class="eq-muted">Sem comparacao: referencia ausente ou incompleta.</p><?php else: ?><p class="<?= $delta['amount'] < 0 ? 'eq-negative' : 'eq-positive' ?>"><?= $delta['amount'] > 0 ? '+' : '' ?><?= eqNumber($delta['amount']) ?><?php if($delta['percent'] !== null): ?> (<?= $delta['percent'] > 0 ? '+' : '' ?><?= number_format($delta['percent'],1,',','.') ?>%)<?php else: ?> / percentual indisponivel<?php endif; ?></p><?php endif; ?></div><?php endforeach; ?>
    </div></section>
    <?php eqChart($data['months'],'Producao mensal - 12 meses',['total'=>'Total','executed'=>'Executadas','sent'=>'Enviadas']); eqChart($data['months'],'Referencias mensais - 12 meses',['lis'=>'LIS-S','leit'=>'LIS-N']); ?>
    <details class="eq-section"><summary>Valores e cobertura por mes</summary><div class="eq-scroll"><table class="c-table eq-readings"><thead><tr><th>Mes</th><th>Dias</th><th>Total</th><th>Executadas</th><th>Enviadas</th><th>Pendentes</th><th>LIS-S</th><th>LIS-N</th></tr></thead><tbody>
    <?php foreach(array_reverse($data['months'],true) as $month=>$row): ?><tr><td><?= date('m/Y',strtotime($month.'-01')) ?></td><td><?= eqNumber($row['days']) ?></td><td><?= eqNumber($row['total']) ?></td><?php foreach(['executed','sent','pending','lis','leit'] as $field): $coverage=$field === 'pending' ? 'executed' : $field; ?><td><?= eqNumber($row[$coverage.'_days'] ? $row[$field] : null) ?><small><?= eqEscape(eqCoverage($row,$coverage)) ?></small></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div></details>
    <?php eqChart($data['daily'],'Producao diaria do mes',['total'=>'Total','executed'=>'Executadas','sent'=>'Enviadas'],true); ?>
    <?php
}
