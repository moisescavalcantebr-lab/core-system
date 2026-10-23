<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
csrf_verify();
$locked = false; $lock = cryptoBinanceConnectionLock($pdo);
try {
    $action = (string)($_POST['action'] ?? '');
    if (!in_array($action, ['save','disconnect'],true)) throw new InvalidArgumentException('Acao indisponivel.');
    $stmt = $pdo->prepare('SELECT GET_LOCK(?,0)'); $stmt->execute([$lock]); $locked = (int)$stmt->fetchColumn() === 1;
    if (!$locked) throw new InvalidArgumentException('Ja existe uma consulta em andamento. Aguarde antes de alterar a conexao.');
    $store = new CryptoBinanceConnectionStore($pdo);
    if ($action === 'disconnect') {
        if (($_POST['confirm_disconnect'] ?? '') !== '1') throw new InvalidArgumentException('Confirme a desconexao.');
        $store->disconnect(); flash('success', 'Conexao removida. O historico foi preservado. A chave continua sob seu controle na Binance.');
    } else {
        if (($_POST['read_only'] ?? '') !== '1') throw new InvalidArgumentException('Confirme o uso de uma chave dedicada somente a leitura.');
        $store->save((string)($_POST['api_key'] ?? ''), (string)($_POST['api_secret'] ?? ''), (int)projectUser()['id']);
        flash('success', 'Conexao salva com protecao. Clique em Atualizar dados da conta para consultar a Binance.');
    }
} catch (InvalidArgumentException|DomainException $e) { flash('error', $e->getMessage()); }
catch (Throwable $e) { flash('error', 'Nao foi possivel alterar a conexao. A conexao anterior foi preservada.'); }
finally {
    unset($_POST['api_key'],$_POST['api_secret']);
    if ($locked) { $stmt=$pdo->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$lock]); }
}
header('Location: ' . PROJECT_URL . '/admin/crypto_binance/index.php', true, 303); exit;
