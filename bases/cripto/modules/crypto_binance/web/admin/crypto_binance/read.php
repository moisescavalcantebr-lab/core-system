<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit;
}
csrf_verify();
$mode = (string)($_POST['mode'] ?? '');
$lock = cryptoBinanceConnectionLock($pdo);
$locked = false;
$readId = null;
try {
    if (!in_array($mode, ['public','account','demo'], true)) throw new InvalidArgumentException('Escolha o tipo de consulta.');
    $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 0)'); $lockStmt->execute([$lock]);
    $locked = (int)$lockStmt->fetchColumn() === 1;
    if (!$locked) throw new InvalidArgumentException('Ja existe uma consulta em andamento neste projeto. Aguarde.');
    if ($mode !== 'demo') {
        $latest = $pdo->query("SELECT TIMESTAMPDIFF(SECOND, created_at, NOW()) FROM crypto_binance_reads WHERE mode <> 'demo' ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($latest !== false && (int)$latest < 15) throw new InvalidArgumentException('Aguarde 15 segundos entre consultas a Binance.');
    }
    if ($mode === 'demo') {
        $snapshot = CryptoBinanceReader::demo();
    } else {
        $symbols = CryptoBinanceReader::symbols((string)($_POST['symbols'] ?? ''));
        unset($_POST['api_key'], $_POST['api_secret']);
        $reader = new CryptoBinanceReader();
        $snapshot = $mode === 'account'
            ? (new CryptoBinanceConnectionStore($pdo))->read($symbols, $reader)
            : $reader->read($symbols);
    }
    $readId = cryptoBinanceSaveRead($pdo, $snapshot, (int)projectUser()['id']);
} catch (InvalidArgumentException|DomainException $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    flash('error', 'Nao foi possivel concluir ou salvar esta consulta. Os registros anteriores foram preservados.');
} finally {
    unset($_POST['api_key'], $_POST['api_secret']);
    if ($locked) { $unlock = $pdo->prepare('SELECT RELEASE_LOCK(?)'); $unlock->execute([$lock]); }
}
header('Location: ' . PROJECT_URL . '/admin/crypto_binance/index.php' . ($readId ? '?read=' . $readId : ''), true, 303);
exit;
