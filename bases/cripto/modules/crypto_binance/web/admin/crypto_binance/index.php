<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$title = 'Binance - leitura';
$readId = max(0, (int)($_GET['read'] ?? 0));
if ($readId) {
    $stmt = $pdo->prepare('SELECT * FROM crypto_binance_reads WHERE id = ?'); $stmt->execute([$readId]); $read = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    $read = $pdo->query('SELECT * FROM crypto_binance_reads ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
}
$snapshot = $read ? json_decode($read['snapshot'], true, 64, JSON_THROW_ON_ERROR) : null;
$history = $pdo->query('SELECT id, mode, status, created_at FROM crypto_binance_reads ORDER BY id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
$connectionStore = new CryptoBinanceConnectionStore($pdo);
$connection = $connectionStore->metadata();
$protectionReady = $connectionStore->protectionAvailable();
ob_start();
?>
<div class="c-page cb-page">
 <div class="c-page-header">
  <div><h1 class="c-page-title">Binance - leitura</h1><p class="c-page-subtitle">Conferencia manual dos dados da corretora</p></div>
  <a class="c-btn-secondary" href="<?= PROJECT_URL ?>/admin/crypto_dca_manager/index.php">DCA Strategy</a>
 </div>
 <div class="c-page-content">
  <?php flash_show(); ?>
  <div class="c-card"><strong>Primeiro teste: leitura dos dados</strong><p>Consulte pares e confira uma amostra Spot. A identificacao dos bots, ciclos e reservas DCA ainda precisa ser validada. Nenhuma compra, venda ou transferencia e realizada por esta tela.</p></div>
  <section class="c-card cb-form" id="cb-connection" data-saved="<?= $connection ? '1' : '0' ?>">
   <h2>Conexao Binance Spot</h2>
   <?php if ($connection): ?><p><strong>Conexao salva.</strong> Credenciais atualizadas em <?= cryptoBinanceEscape($connection['updated_at']) ?> UTC. A validade e conferida ao consultar a conta.</p>
   <?php else: ?><p>Salve uma chave HMAC dedicada somente a leitura para atualizar sua conta sem digitar as credenciais novamente.</p><?php endif; ?>
   <?php if (!$protectionReady): ?><p role="alert">A protecao da conexao ainda nao esta disponivel neste ambiente. As consultas publicas e a demonstracao continuam funcionando.</p><?php else: ?>
   <details><summary><?= $connection ? 'Atualizar conexao' : 'Salvar conexao' ?></summary>
    <form method="post" action="<?= PROJECT_URL ?>/admin/crypto_binance/connection.php" autocomplete="off">
     <?= csrf_field() ?><input type="hidden" name="action" value="save">
     <div class="cb-grid">
      <div><label for="cb-key">API Key de leitura</label><input class="c-input" id="cb-key" name="api_key" type="password" autocomplete="new-password" maxlength="256" required></div>
      <div><label for="cb-secret">Secret Key</label><input class="c-input" id="cb-secret" name="api_secret" type="password" autocomplete="new-password" maxlength="256" required></div>
     </div>
     <p>As credenciais ficam criptografadas no banco deste projeto. A chave de protecao fica fora do site. As chaves salvas nunca sao exibidas no formulario.</p>
     <label><input type="checkbox" name="read_only" value="1" required> Estou usando uma chave dedicada somente a leitura, sem negociacao, saque ou transferencia.</label>
     <p><button class="c-btn-secondary cb-primary" type="submit">Salvar conexao</button></p>
    </form>
   </details>
   <?php endif; ?>
   <?php if ($connection): ?>
   <form method="post" action="<?= PROJECT_URL ?>/admin/crypto_binance/connection.php" onsubmit="return window.confirm('Remover a conexao salva? O historico sera mantido.');">
    <?= csrf_field() ?><input type="hidden" name="action" value="disconnect"><input type="hidden" name="confirm_disconnect" value="1">
    <button class="c-btn-secondary" type="submit">Desconectar</button>
   </form><p class="c-muted">Desconectar remove as credenciais salvas. O historico permanece; para revogar a chave na Binance, use o gerenciamento de API da corretora.</p>
   <?php endif; ?>
  </section>
  <form class="c-card cb-form" method="post" action="<?= PROJECT_URL ?>/admin/crypto_binance/read.php" autocomplete="off">
   <?= csrf_field() ?>
   <label for="cb-symbols">Pares para conferir (ate 3)</label>
   <input class="c-input" id="cb-symbols" name="symbols" maxlength="80" value="<?= cryptoBinanceEscape(implode(', ', ($snapshot['mode'] ?? '') !== 'demo' ? ($snapshot['symbols'] ?? ['BTCUSDT']) : ['BTCUSDT'])) ?>" placeholder="BTCUSDT, BTCUSDC">
   <p class="c-muted">A consulta publica funciona sem chave. A consulta da conta utiliza sua conexao salva.</p>
   <p>Negociacoes: amostra de ate 100 por par nas ultimas 24 horas. Ordens abertas: ate 100 por par. Saldos: somente a abrangencia retornada pela API Spot; um saldo zero nao comprova ausencia de valores nos bots.</p>
   <div class="cb-actions">
    <?php if ($connection && $protectionReady): ?><button class="c-btn-secondary cb-primary" type="submit" name="mode" value="account">Atualizar dados da conta</button><?php endif; ?>
    <button class="c-btn-secondary" type="submit" name="mode" value="public">Consultar precos e pares</button><button class="c-btn-secondary" type="submit" name="mode" value="demo">Testar com demonstracao</button>
   </div>
  </form>
  <?php if ($snapshot): ?>
   <section class="c-card">
    <h2><?= $snapshot['mode'] === 'demo' ? 'Demonstracao - valores ficticios' : ($snapshot['mode'] === 'account' ? 'Consulta da conta Spot' : 'Consulta publica') ?></h2>
    <p>Consultado em <?= cryptoBinanceEscape($snapshot['observed_at']) ?>. Resultado: <?= cryptoBinanceEscape(['success'=>'consulta concluida', 'partial'=>'cobertura parcial', 'failed'=>'consulta falhou'][$snapshot['status']] ?? $snapshot['status']) ?>.</p>
    <?php if ($snapshot['mode'] === 'demo'): ?><p><strong>Estes valores servem apenas para testar a tela. Nao representam sua conta nem cotacoes atuais.</strong></p><?php endif; ?>
    <?php if (isset($snapshot['window_start'])): ?><p>Janela da amostra de negociacoes: <?= cryptoBinanceEscape($snapshot['window_start']) ?> a <?= cryptoBinanceEscape($snapshot['window_end']) ?>.</p><?php endif; ?>
    <h3>O que conseguimos consultar</h3>
    <div class="cb-checks"><?php foreach ($snapshot['checks'] as $name=>$check): ?>
     <article class="cb-check"><strong><?= cryptoBinanceEscape(cryptoBinanceLabel($name)) ?></strong><span><?= cryptoBinanceEscape(cryptoBinanceState($check['state'])) ?></span>
      <?php if (isset($check['count'])): ?><small><?= (int)$check['count'] ?> registros<?= !empty($check['limit_reached']) ? ' - limite da amostra atingido' : '' ?></small><?php endif; ?>
      <?php if (isset($check['code'])): ?><small><?= cryptoBinanceEscape(CryptoBinanceReader::errorLabel($check['code'])) ?> (<?= cryptoBinanceEscape($check['code']) ?>)</small><?php endif; ?>
     </article>
    <?php endforeach; ?></div>
    <p class="c-muted">Negociacoes Spot ficam pendentes de associacao aos bots. Esta consulta nao calcula o lucro consolidado nem altera seus ativos e ciclos manuais.</p>
   </section>
   <?php if ($snapshot['markets']): ?>
    <section class="c-card"><h3>Pares consultados</h3><div class="cb-scroll"><table class="c-table"><thead><tr><th>Par</th><th>Preco</th><th>Moeda</th><th>Estado</th></tr></thead><tbody><?php foreach ($snapshot['markets'] as $row): ?><tr><td><?= cryptoBinanceEscape($row['symbol']) ?></td><td><?= cryptoBinanceEscape($row['price']) ?></td><td><?= cryptoBinanceEscape($row['quote']) ?></td><td><?= cryptoBinanceEscape($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
   <?php endif; ?>
   <?php if ($snapshot['balances']): ?>
    <section class="c-card"><h3>Saldos Spot retornados</h3><div class="cb-scroll"><table class="c-table"><thead><tr><th>Ativo</th><th>Livre</th><th>Bloqueado</th></tr></thead><tbody><?php foreach ($snapshot['balances'] as $row): ?><tr><td><?= cryptoBinanceEscape($row['asset']) ?></td><td><?= cryptoBinanceEscape($row['free']) ?></td><td><?= cryptoBinanceEscape($row['locked']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
   <?php elseif (($snapshot['checks']['balances']['state'] ?? '') === 'confirmed'): ?><div class="c-card">A API Spot nao retornou saldos diferentes de zero. Isso nao confirma o saldo dos bots DCA.</div><?php endif; ?>
   <?php foreach (['orders'=>'Ordens abertas', 'trades'=>'Negociacoes da amostra'] as $kind=>$label): ?>
    <?php if ($snapshot[$kind]): ?><section class="c-card"><h3><?= $label ?></h3><div class="cb-scroll"><table class="c-table"><thead><tr><th>Par</th><th>Ordem</th><th>Lado</th><th>Quantidade</th><th>Preco</th><th><?= $kind === 'trades' ? 'Taxa' : 'Estado' ?></th></tr></thead><tbody>
    <?php foreach ($snapshot[$kind] as $row): ?><tr><td><?= cryptoBinanceEscape($row['symbol']) ?></td><td><?= cryptoBinanceEscape($row['orderId']) ?></td><td><?= cryptoBinanceEscape($kind === 'trades' ? (!empty($row['isBuyer']) ? 'Compra' : 'Venda') : ($row['side'] ?? '-')) ?></td><td><?= cryptoBinanceEscape($row[$kind === 'trades' ? 'qty' : 'origQty'] ?? '-') ?></td><td><?= cryptoBinanceEscape($row['price'] ?? '-') ?></td><td><?= cryptoBinanceEscape($kind === 'trades' ? ($row['commission'] ?? '-') . ' ' . ($row['commissionAsset'] ?? '') : ($row['status'] ?? '-')) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section><?php endif; ?>
   <?php endforeach; ?>
  <?php else: ?><div class="c-card">Comece pela demonstracao ou pela consulta publica, sem informar chaves.</div><?php endif; ?>
  <?php if ($history): ?><section class="c-card"><h3>Ultimas consultas</h3><ul><?php foreach ($history as $item): ?><li><a href="?read=<?= (int)$item['id'] ?>"><?= cryptoBinanceEscape($item['created_at']) ?> - <?= cryptoBinanceEscape(['demo'=>'Demonstracao','public'=>'Publica','account'=>'Conta Spot'][$item['mode']]) ?></a> - <?= cryptoBinanceEscape(['success'=>'concluida','partial'=>'parcial','failed'=>'falhou'][$item['status']]) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
 </div>
</div>
<style>
.cb-page .cb-primary{background:var(--color-primary,#3b82f6);color:#fff;border-color:var(--color-primary,#3b82f6);cursor:pointer}.cb-page .cb-primary:hover{background:var(--color-primary-hover,#2563eb)}.cb-page .c-card a:not(.c-btn-secondary){color:var(--color-primary,#60a5fa)}.cb-page .c-card{margin-bottom:16px;min-width:0}.cb-form>label,.cb-grid label{display:block;margin-bottom:6px}.cb-form>input{width:100%;box-sizing:border-box}.cb-form details{margin:16px 0;padding:12px;border:1px solid var(--border-color,#454545);border-radius:8px}.cb-form summary{cursor:pointer;font-weight:600}.cb-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px}.cb-grid input{width:100%;box-sizing:border-box}.cb-actions{display:flex;flex-wrap:wrap;gap:8px}.cb-checks{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.cb-check{border:1px solid var(--border-color,#454545);border-radius:8px;padding:12px;overflow-wrap:anywhere}.cb-check span,.cb-check small{display:block;margin-top:5px}.cb-scroll{overflow-x:auto;max-width:100%}.cb-scroll td{white-space:nowrap}.cb-form p{overflow-wrap:anywhere} @media(max-width:700px){.cb-grid,.cb-checks{grid-template-columns:1fr}.cb-actions>*{width:100%}.cb-page .c-page-header{flex-wrap:wrap;gap:12px}}
</style>
<?php $content=ob_get_clean();require APP_PATH . '/views/layout_admin.php';
