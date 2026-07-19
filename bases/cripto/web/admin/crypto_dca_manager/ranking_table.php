<?php
declare(strict_types=1);

function cryptoDcaRankingTable(array $rows, string $mode): void
{
    ?>
    <div class="c-table-wrap">
        <table class="c-table">
            <thead><tr><th>Ativo</th><th>Resultado</th><th>%</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string)$row['symbol']) ?></strong><br><small><?= htmlspecialchars((string)$row['name']) ?></small></td>
                    <td><?= cryptoDcaMoney((float)$row['profit']) ?></td>
                    <td><?= cryptoDcaPercent((float)$row['percent']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="3">Sem dados.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
