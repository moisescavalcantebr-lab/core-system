<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
function teamCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function teamRoute(string $path, array $post = [], array $get = [], int $actor = 1): string
{
    $code = '$_SERVER["REQUEST_METHOD"]='.var_export($post ? 'POST' : 'GET', true).';$_POST='.var_export($post, true).';$_GET='.var_export($get, true).';$actor='.$actor.';require '.var_export($path, true).';';
    $process = proc_open([PHP_BINARY, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    teamCheck(proc_close($process) === 0, 'Rota falhou: '.$err);
    teamCheck(!str_contains($out, 'Warning:') && !str_contains($out, 'Fatal error:'), 'Aviso na rota: '.$out);
    return $out;
}
$db = (require $root.'/env/env.production.php')['db'];
$pdo = new PDO('mysql:host='.$db['host'].';charset=utf8mb4', $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'test_team_formations_'.bin2hex(random_bytes(5));
$fixture = sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec((string)file_get_contents($root.'/bases/futebol/app/database/schema.sql'));
    $pdo->exec((string)file_get_contents($root.'/modules/jogadores/database/schema.sql'));
    $schema = (string)file_get_contents($root.'/modules/meu_time/database/schema.sql');
    $legacy = str_replace(["    formations_count TINYINT UNSIGNED NOT NULL DEFAULT 1,\n", "    team_key ENUM('time_1','time_2') NOT NULL DEFAULT 'time_1',\n"], '', str_replace("\r\n", "\n", $schema));
    $legacy = str_replace(["    time_1_name VARCHAR(80) NOT NULL DEFAULT 'Time 1',\n", "    time_2_name VARCHAR(80) NOT NULL DEFAULT 'Time 2',\n"], '', $legacy);
    $pdo->exec($legacy);
    $pdo->exec("INSERT INTO project_users (id,name,email,username,password,role,status) VALUES (991,'Admin','team-admin@example.com','team-admin','test','ADMIN','active'),(992,'Cliente','team-client@example.com','team-client','test','CLIENT','active')");
    $position = (int)$pdo->query("SELECT id FROM player_positions WHERE code = 'GO1' LIMIT 1")->fetchColumn();
    for ($i = 1; $i <= 5; $i++) {
        $pdo->prepare('INSERT INTO players (id,name,nickname,position_id,status) VALUES (?,?,?,?,?)')->execute([$i, 'Jogador '.$i, 'Atleta '.$i, $position, $i === 5 ? 'inactive' : 'active']);
    }
    $pdo->exec("INSERT INTO team_roster (player_id,status,slot_group,slot_index) VALUES (1,'active','goleiro',0),(2,'inactive',NULL,NULL)");
    foreach (['app/bootstrap', 'app/helpers', 'app/views', 'web/admin/meu_time'] as $dir) mkdir($fixture.'/'.$dir, 0700, true);
    foreach (glob($root.'/modules/meu_time/web/admin/meu_time/*.php') as $file) copy($file, $fixture.'/web/admin/meu_time/'.basename($file));
    foreach (['project_auth.php', 'csrf.php'] as $file) copy($root.'/bases/futebol/app/helpers/'.$file, $fixture.'/app/helpers/'.$file);
    $connection = '$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4', true).','.var_export($db['user'], true).','.var_export($db['pass'], true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php', '<?php ob_start();define("PROJECT_PATH",'.var_export($fixture, true).');define("APP_PATH",PROJECT_PATH."/app");define("PROJECT_URL","/projects/team-test/web");$_SESSION=["project_user_id"=>$actor===1?991:992,"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";function flash($type,$msg){echo $msg;}function flash_show(){}function redirect($url){echo "REDIRECT:".$url;exit;}');
    $css = '';
    foreach (['core_base.css','admin/layout.css','admin/components.css','themes/dark.css'] as $file) $css .= file_get_contents($root.'/bases/futebol/web/assets/css/'.$file);
    file_put_contents($fixture.'/app/views/layout_admin.php', '<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css, true).'."body{padding:16px}.c-content{margin:0!important;max-width:1200px}</style></head><body class=c-app><main class=c-content>".$content."</main></body></html>";');
    $routes = $fixture.'/web/admin/meu_time/';
    $read = fn(int $id) => $pdo->query('SELECT * FROM team_roster WHERE player_id='.$id)->fetch(PDO::FETCH_ASSOC);
    $playersBefore = $pdo->query('SELECT * FROM players ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $one = teamRoute($routes.'index.php');
    teamCheck(substr_count($one, 'class="c-card c-team-field-card"') === 1, 'Nao iniciou com um campo');
    teamCheck($read(1)['team_key'] === 'time_1' && $read(1)['status'] === 'active', 'Migracao mudou formacao existente');
    $post = ['_csrf' => 'test', 'player_id' => 2, 'team_key' => 'time_2'];
    teamCheck(str_contains(teamRoute($routes.'roster_store.php', $post), 'Ative o segundo'), 'Segundo time desativado aceitou jogador');
    teamCheck(str_contains(teamRoute($routes.'formations_save.php', ['_csrf'=>'wrong','second_team'=>1]), 'CSRF'), 'CSRF ignorado');
    teamCheck(str_contains(teamRoute($routes.'formations_save.php', ['_csrf'=>'test','second_team'=>1], [], 2), 'Acesso negado'), 'Cliente alterou formacoes');
    teamRoute($routes.'formations_save.php', ['_csrf'=>'test','second_team'=>1]);
    teamRoute($routes.'roster_store.php', $post);
    teamCheck($read(2)['team_key'] === 'time_2' && $read(2)['status'] === 'active', 'Segundo time nao recebeu jogador');
    teamCheck($read(1)['slot_index'] === $read(2)['slot_index'], 'Mesma posicao nao funcionou em campos independentes');
    teamCheck(str_contains(teamRoute($routes.'roster_store.php', [...$post,'player_id'=>1]), 'ja esta em campo'), 'Duplicou atleta entre times');
    teamRoute($routes.'roster_store.php', [...$post,'player_id'=>3]);
    teamCheck($read(3)['status'] === 'inactive', 'Vaga ocupada sobrepos jogador');
    teamRoute($routes.'roster_store.php', [...$post,'player_id'=>5]);
    teamCheck(!$read(5), 'Atleta inativo escalado');
    $two = teamRoute($routes.'index.php');
    teamCheck(substr_count($two, 'class="c-card c-team-field-card"') === 2, 'Nao mostrou dois campos');
    teamCheck(!str_contains($two, 'name="player_id" value="1"') && !str_contains($two, 'name="player_id" value="2"'), 'Jogador escalado apareceu disponivel');
    teamCheck(str_contains($two, 'name="player_id" value="3"'), 'Jogador sem vaga sumiu dos disponiveis');
    teamRoute($routes.'roster_remove.php', ['_csrf'=>'test'], ['id'=>$read(2)['id']]);
    teamCheck($read(2)['status'] === 'inactive', 'Remover nao liberou jogador');
    teamRoute($routes.'roster_store.php', [...$post,'team_key'=>'time_1']);
    teamCheck($read(2)['status'] === 'inactive', 'Sobrepos Time 1 ocupado');
    teamRoute($routes.'roster_store.php', $post);
    teamRoute($routes.'formations_save.php', ['_csrf'=>'test']);
    teamCheck($read(1)['status'] === 'active' && $read(2)['status'] === 'inactive' && $read(2)['slot_group'] === null, 'Desativar afetou Time 1 ou nao liberou Time 2');
    teamRoute($routes.'formations_save.php', ['_csrf'=>'test','second_team'=>1]);
    teamCheck($read(2)['status'] === 'inactive', 'Reativar escalou jogador automaticamente');
    teamRoute($routes.'roster_store.php', $post);
    require $root.'/modules/meu_time/web/admin/meu_time/formations_helpers.php';
    $before = $pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    teamEnsureFormationsSchema($pdo); teamEnsureFormationsSchema($pdo); $pdo->exec($schema);
    teamCheck($before === $pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Reaplicar schema alterou elenco');
    teamCheck($playersBefore === $pdo->query('SELECT * FROM players ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Alterou cadastros de jogadores');
    if (!is_dir($root.'/_deploy')) mkdir($root.'/_deploy',0700,true);
    file_put_contents($root.'/_deploy/team-one-preview.html', $one);
    file_put_contents($root.'/_deploy/team-two-preview.html', teamRoute($routes.'index.php'));
    $beforeRename = $pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $names = ['_csrf'=>'test', 'time_1_name'=>'Azul & Branco', 'time_2_name'=>'Equipe <Verde>', 'name'=>'Meu Time'];
    teamRoute($routes.'save.php', $names);
    teamCheck($beforeRename === $pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC), 'Renomear alterou formacoes');
    teamCheck($pdo->query('SELECT time_1_name FROM team_profile WHERE id=1')->fetchColumn() === 'Azul & Branco', 'Nome do Time 1 nao foi salvo');
    $renamed = teamRoute($routes.'index.php');
    teamCheck(str_contains($renamed,'Azul &amp; Branco') && str_contains($renamed,'Equipe &lt;Verde&gt;'), 'Nomes nao aparecem escapados');
    teamCheck(str_contains($renamed,'title="Adicionar ao Equipe &lt;Verde&gt;"'), 'Destino nao usa nome personalizado');
    $form = teamRoute($routes.'config.php');
    teamCheck(str_contains($form,'name="time_1_name"') && str_contains($form,'value="Equipe &lt;Verde&gt;"'), 'Formulario nao recuperou nomes');
    teamRoute($routes.'save.php', ['_csrf'=>'test','name'=>'Meu Time']);
    teamCheck($pdo->query('SELECT time_1_name FROM team_profile WHERE id=1')->fetchColumn() === 'Azul & Branco', 'Formulario antigo apagou nomes');
    teamRoute($routes.'save.php', [...$names,'time_1_name'=>str_repeat('x',81)]);
    teamCheck($pdo->query('SELECT time_1_name FROM team_profile WHERE id=1')->fetchColumn() === 'Azul & Branco', 'Nome longo aceito');
    teamRoute($routes.'save.php', [...$names,'time_1_name'=>['invalido']]);
    teamCheck($pdo->query('SELECT time_1_name FROM team_profile WHERE id=1')->fetchColumn() === 'Azul & Branco', 'Entrada invalida alterou nome');
    teamRoute($routes.'formations_save.php', ['_csrf'=>'test']);
    teamRoute($routes.'formations_save.php', ['_csrf'=>'test','second_team'=>1]);
    teamCheck($pdo->query('SELECT time_2_name FROM team_profile WHERE id=1')->fetchColumn() === 'Equipe <Verde>', 'Desativacao apagou nome');
    // Restore the roster after testing the independent activation control.
    teamRoute($routes.'roster_store.php', $post);
    teamCheck($pdo->query('SELECT COUNT(*) FROM team_roster')->fetchColumn() == count($beforeRename), 'Renomeacao alterou quantidade de jogadores');
    file_put_contents($root.'/_deploy/team-renamed-preview.html', $renamed);
    file_put_contents($root.'/_deploy/team-config-preview.html', $form);
    teamRoute($routes.'save.php', [...$names,'time_1_name'=>'  ','time_2_name'=>'']);
    teamCheck($pdo->query('SELECT time_1_name FROM team_profile WHERE id=1')->fetchColumn() === 'Time 1' && $pdo->query('SELECT time_2_name FROM team_profile WHERE id=1')->fetchColumn() === 'Time 2', 'Campos vazios nao recuperaram padroes');
    echo "PASS: migracao preservada, dois campos independentes, jogador unico, vagas, disponiveis, desativacao, reativacao, CSRF e permissoes.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if (is_dir($fixture)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($fixture);
    }
}
