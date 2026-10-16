<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
function cardCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function cardRoute(string $path, array $post = [], int $actor = 1): string
{
    $code = '$_SERVER["REQUEST_METHOD"]='.var_export($post?'POST':'GET',true).';$_POST='.var_export($post,true).';$actor='.$actor.';require '.var_export($path,true).';';
    $process = proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    cardCheck(proc_close($process)===0,'Falha na rota: '.$error.' '.$out);
    cardCheck(!str_contains($out,'Warning:')&&!str_contains($out,'Fatal error:'),'Aviso na rota: '.$out);
    return $out;
}
$db = (require $root.'/env/env.production.php')['db'];
$pdo = new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database = 'test_player_cards_'.bin2hex(random_bytes(5));
$fixture = sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec(file_get_contents($root.'/bases/futebol/app/database/schema.sql'));
    $pdo->exec(file_get_contents($root.'/modules/jogadores/database/schema.sql'));
    $pdo->exec("INSERT INTO project_users (id,name,email,username,password,role,status) VALUES (991,'Admin','cards-admin@example.com','cards-admin','test','ADMIN','active'),(992,'Cliente','cards-client@example.com','cards-client','test','CLIENT','active')");
    foreach (['app/bootstrap','app/helpers','app/views','web/admin/jogadores','web/admin/meu_time'] as $dir) mkdir($fixture.'/'.$dir,0700,true);
    foreach (glob($root.'/modules/jogadores/web/admin/jogadores/*.php') as $file) copy($file,$fixture.'/web/admin/jogadores/'.basename($file));
    copy($root.'/modules/meu_time/web/admin/meu_time/lineup_helpers.php',$fixture.'/web/admin/meu_time/lineup_helpers.php');
    foreach (['project_auth.php','csrf.php','settings.php'] as $file) copy($root.'/bases/futebol/app/helpers/'.$file,$fixture.'/app/helpers/'.$file);
    $connection = '$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php','<?php ob_start();define("PROJECT_PATH",'.var_export($fixture,true).');define("APP_PATH",PROJECT_PATH."/app");define("PROJECT_URL","/projects/cards-test/web");$project=["plan_name"=>"Plano Start","billing_cycle"=>"monthly"];$_SESSION=["project_user_id"=>$actor===1?991:992,"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";require APP_PATH."/helpers/settings.php";function flash($type,$msg){echo $msg;}function flash_show(){}function redirect($url){echo "REDIRECT:".$url;exit;}');
    $css='';foreach (['core_base.css','admin/layout.css','admin/components.css'] as $file) $css.=file_get_contents($root.'/bases/futebol/web/assets/css/'.$file);
    file_put_contents($fixture.'/app/views/layout_admin.php','<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css,true).'."body{margin:0}.c-content{margin:0!important;padding:16px;max-width:1400px}</style><style id=card-test-theme></style></head><body class=c-app><main class=c-content>".$content."</main></body></html>";');
    $positions=[];foreach ($pdo->query("SELECT code,id FROM player_positions WHERE code IN ('GO1','GO2','CA1','ZC1')")->fetchAll(PDO::FETCH_ASSOC) as $row) $positions[$row['code']]=$row['id'];
    for ($i=1;$i<=14;$i++) {
        $code = $i===1?'GO1':($i===2?'GO2':'CA1');
        $name = $i===3?'Pessoa <teste>':($i===4?'AlexandreNomeMuitoLongo':'Jogador '.$i);
        $nickname = $i===3?'Nome <teste>':($i===4?'NomeMuitoLongo':'Atleta '.$i);
        $avatar = $i===1?'storage/uploads/avatars/card-photo.svg':null;
        $pdo->prepare('INSERT INTO players (id,name,nickname,position_id,shirt_number,status,avatar) VALUES (?,?,?,?,?,?,?)')->execute([$i,$name,$nickname,$positions[$code],$i,'active',$avatar]);
    }
    $route=$fixture.'/web/admin/jogadores/index.php';
    cardCheck(str_contains(cardRoute($route,[],2),'Acesso negado'),'Usuario comum viu gestao');
    $standalone=cardRoute($route);
    cardCheck(substr_count($standalone,'data-ranking-state="pending"')===14,'Reserva de ranking ausente');
    cardCheck(str_contains($standalone,'Sem formação definida'),'Jogadores exige Meu Time');
    cardCheck(!str_contains($standalone,'c-player-ranking-badge')&&!str_contains($standalone,'c-player-access-badge'),'Classificacao antiga ou acesso continua no destaque');
    $pdo->exec(file_get_contents($root.'/modules/meu_time/database/schema.sql'));
    $pdo->exec("UPDATE team_profile SET formations_count=2,time_1_name='Sabia <Um>',time_2_name='Sabia Dois' WHERE id=1");
    $pdo->exec("INSERT INTO team_roster (player_id,status,team_key,slot_group,slot_index) VALUES (1,'active','time_1','goleiro',0),(2,'active','time_2','goleiro',0),(3,'active','time_1','atacante',99)");
    $before=$pdo->query('SELECT * FROM players ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $roster=$pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $html=cardRoute($route);
    cardCheck(str_contains($html,'Sabia &lt;Um&gt;')&&str_contains($html,'Sabia Dois')&&str_contains($html,'Disponível'),'Formacoes ou disponiveis incorretos');
    cardCheck(str_contains($html,'Nome &lt;teste&gt;')&&!str_contains($html,'>Nome <teste><'),'Nome nao foi escapado');
    cardCheck(substr_count($html,'class="c-player-row-card"')===14,'Quantidade de cards incorreta');
    cardCheck(substr_count($html,'data-deactivate-player')===15,'Acao de desativacao ausente');
    cardCheck(str_contains($html,'name="_csrf" value="test"'),'Formulario de desativacao sem CSRF');
    cardCheck($before===$pdo->query('SELECT * FROM players ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Renderizacao alterou jogadores');
    cardCheck($roster===$pdo->query('SELECT * FROM team_roster ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),'Renderizacao alterou formacoes');
    if (!is_dir($root.'/_deploy')) mkdir($root.'/_deploy',0700,true);
    file_put_contents($root.'/_deploy/player-cards-preview.html',$html);
    file_put_contents($root.'/_deploy/player-cards-standalone-preview.html',$standalone);
    $pdo->exec("UPDATE team_profile SET formations_count=1 WHERE id=1");
    cardCheck(!str_contains(cardRoute($route),'Sabia Dois'),'Time desativado aparece no card');
    $toggle=$fixture.'/web/admin/jogadores/toggle.php';
    cardCheck(str_contains(cardRoute($toggle,['_csrf'=>'invalid']),'CSRF'),'Desativacao ignora CSRF');
    echo "PASS: cards, ranking pendente sem pontuacao antiga, formacoes opcionais/validas, escape, acesso, CSRF e dados preservados.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if (is_dir($fixture)) {
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($fixture);
    }
}
