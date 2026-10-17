<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__, 2);
define('PROJECT_URL', '/projects/football-test/web');
function trackingCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function trackingRoute(string $path, array $post = [], array $get = [], int $actor = 1): string
{
    $code = '$_SERVER["REQUEST_METHOD"]='.var_export($post ? 'POST' : 'GET',true).';$_SERVER["PHP_SELF"]='.var_export('/web/admin/'.basename(dirname($path)).'/'.basename($path),true).';$_POST='.var_export($post,true).';$_GET='.var_export($get,true).';$actor='.$actor.';require '.var_export($path,true).';';
    $process = proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    trackingCheck(proc_close($process)===0,'Falha na rota '.basename($path).': '.$err.' '.$out);
    trackingCheck(!str_contains($out,'Warning:')&&!str_contains($out,'Fatal error:'),'Aviso na rota: '.$out);
    return $out;
}
$db = (require $root.'/env/env.production.php')['db'];
$pdo = new PDO('mysql:host='.$db['host'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database = 'test_football_tracking_'.bin2hex(random_bytes(5));
$fixture = sys_get_temp_dir().'/'.$database;
$pdo->exec('CREATE DATABASE `'.$database.'`');
try {
    $pdo->exec('USE `'.$database.'`');
    $pdo->exec(file_get_contents($root.'/bases/futebol/app/database/schema.sql'));
    foreach (['jogadores','meu_time','competicoes','partidas','classificacao'] as $slug) $pdo->exec(file_get_contents($root.'/modules/'.$slug.'/database/schema.sql'));
    foreach (['app/bootstrap','app/helpers','app/views','web/admin','modules/classificacao'] as $dir) mkdir($fixture.'/'.$dir,0700,true);
    foreach (['meu_time','competicoes','partidas','classificacao','treino'] as $slug) {
        mkdir($fixture.'/web/admin/'.$slug,0700,true);
        foreach (glob($root.'/modules/'.$slug.'/web/admin/'.$slug.'/*.php') as $file) copy($file,$fixture.'/web/admin/'.$slug.'/'.basename($file));
    }
    copy($root.'/modules/classificacao/module.json',$fixture.'/modules/classificacao/module.json');
    foreach (['project_auth.php','csrf.php'] as $file) copy($root.'/bases/futebol/app/helpers/'.$file,$fixture.'/app/helpers/'.$file);
    $connection = '$pdo=new PDO('.var_export('mysql:host='.$db['host'].';dbname='.$database.';charset=utf8mb4',true).','.var_export($db['user'],true).','.var_export($db['pass'],true).',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);';
    file_put_contents($fixture.'/app/bootstrap/project_bootstrap.php','<?php ob_start();define("PROJECT_PATH",'.var_export($fixture,true).');define("APP_PATH",PROJECT_PATH."/app");define("PROJECT_URL","/projects/football-test/web");$_SESSION=["project_user_id"=>$actor===1?991:992,"_csrf"=>"test"];'.$connection.'require APP_PATH."/helpers/project_auth.php";require APP_PATH."/helpers/csrf.php";function flash($type,$msg){$_SESSION["flash"]=$msg;echo $msg;}function flash_show(){}function redirect($url){echo "REDIRECT:".$url;exit;}function projectPlanName(){return is_file(PROJECT_PATH."/test-plan") ? trim(file_get_contents(PROJECT_PATH."/test-plan")) : "Plano Start";}function projectPlanAllows($key,$default=false){return projectPlanName()!=="Plano Gratis";}function projectPlanLimit($key,$default=0){return 100;}function projectPlanList($key,$default=[]){return $default;}');
    $css = '';
    foreach (['core_base.css','admin/layout.css','admin/components.css','themes/dark.css'] as $file) $css .= file_get_contents($root.'/bases/futebol/web/assets/css/'.$file);
    file_put_contents($fixture.'/app/views/layout_admin.php','<?php echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content=\"width=device-width,initial-scale=1\"><style>".'.var_export($css,true).'."body{padding:16px}.c-content{margin:0!important;max-width:1200px}</style></head><body class=c-app><main class=c-content>".$content."</main></body></html>";');
    $pdo->exec("INSERT INTO project_users (id,name,email,username,password,role,status) VALUES (991,'Admin','tracking-admin@example.com','tracking-admin','test','ADMIN','active'),(992,'Cliente','tracking-client@example.com','tracking-client','test','CLIENT','active')");
    $routes = $fixture.'/web/admin/';
    require $routes.'partidas/lineup_helpers.php';
    require $routes.'competicoes/competition_helpers.php';
    require $routes.'classificacao/fields.php';
    matchLineupEnsureSchema($pdo); matchLineupOwnTeam($pdo); competitionEnsureTrackingSchema($pdo);
    $pdo->exec("UPDATE team_profile SET name='Sabia FC',formations_count=2,time_1_name='Sabia A',time_2_name='Sabia B',team_type='custom',starters_count=2,custom_slots_json='{\"GO\":1,\"CA1\":1}' WHERE id=1");
    foreach ([1=>'GO1',2=>'CA1',3=>'GO2',4=>'CA2',5=>'GO3'] as $id=>$code) {
        $position = (int)$pdo->query("SELECT id FROM player_positions WHERE code='$code' LIMIT 1")->fetchColumn();
        $pdo->prepare('INSERT INTO players (id,name,nickname,position_id,status) VALUES (?,?,?,?,?)')->execute([$id,'Jogador '.$id,'Atleta '.$id,$position,'active']);
        if ($id<5) $pdo->prepare('INSERT INTO team_roster (player_id,status,team_key,slot_group,slot_index) VALUES (?,?,?,?,0)')->execute([$id,'active',$id<3?'time_1':'time_2',$id%2===1?'goleiro':'atacante']);
    }
    // Legacy data must remain intact and must not be used as the new lineup source.
    $pdo->exec("CREATE TABLE training_roster (id INT PRIMARY KEY,player_id INT); INSERT INTO training_roster VALUES (42,5); CREATE TABLE training_settings (id INT PRIMARY KEY,custom_slots_json TEXT); INSERT INTO training_settings VALUES (42,'legacy')");
    $champ = ['_csrf'=>'test','name'=>'Campeonato 2026','context'=>'external','type'=>'championship','status'=>'active','official_position'=>'7'];
    trackingCheck(str_contains(trackingRoute($routes.'competicoes/store.php',[...$champ,'_csrf'=>'invalid']),'CSRF'),'CSRF ignorado');
    trackingCheck(str_contains(trackingRoute($routes.'competicoes/store.php',$champ,[],2),'Acesso negado'),'Cliente criou competicao');
    trackingRoute($routes.'competicoes/store.php',$champ);
    $champId = (int)$pdo->query("SELECT id FROM competitions WHERE name='Campeonato 2026'")->fetchColumn();
    trackingCheck($champId>0,'Campeonato nao criado');
    $training = ['_csrf'=>'test','name'=>'Treinos de outubro','context'=>'internal','type'=>'training','status'=>'active'];
    trackingRoute($routes.'competicoes/store.php',$training);
    $trainingId = (int)$pdo->query("SELECT id FROM competitions WHERE name='Treinos de outubro'")->fetchColumn();
    trackingCheck($trainingId>0,'Treino nao criado sem addon antigo');
    $game = ['_csrf'=>'test','competition_id'=>$trainingId,'participant_a'=>'Nome indevido','participant_b'=>'Outro indevido','status'=>'scheduled','lineup_mode'=>'automatic','match_date'=>'2026-11-01T10:00'];
    trackingRoute($routes.'partidas/store.php',$game);
    $matchId = (int)$pdo->query('SELECT MAX(id) FROM matches')->fetchColumn();
    $match = $pdo->query('SELECT * FROM matches WHERE id='.$matchId)->fetch(PDO::FETCH_ASSOC);
    $lineup = $pdo->query('SELECT * FROM match_lineup WHERE match_id='.$matchId.' ORDER BY player_id')->fetchAll(PDO::FETCH_ASSOC);
    trackingCheck(count($lineup)===4 && $lineup[0]['lineup_team']==='team_1' && $lineup[2]['lineup_team']==='team_2','Nao copiou as duas formacoes');
    trackingCheck($match['participant_a']==='Sabia A' && $match['participant_b']==='Sabia B' && $match['lineup_snapshot_at']!==null,'Nomes ou marcador nao preservados');
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM match_confirmations WHERE match_id='.$matchId)->fetchColumn()===4,'Confirmou atleta fora das formacoes');
    $pdo->exec("UPDATE team_profile SET formations_count=1,time_1_name='Novo A',custom_slots_json='{\"GO\":1}' WHERE id=1; UPDATE team_roster SET status='inactive' WHERE player_id=3");
    matchLineupSyncTrainingSnapshot($pdo,$matchId);
    matchLineupAutoAssignInternal($pdo,$matchId);
    trackingCheck($lineup===$pdo->query('SELECT * FROM match_lineup WHERE match_id='.$matchId.' ORDER BY player_id')->fetchAll(PDO::FETCH_ASSOC),'Mudanca em Meu Time remontou treino existente');
    $saved = $pdo->query('SELECT * FROM matches WHERE id='.$matchId)->fetch(PDO::FETCH_ASSOC);
    trackingCheck($saved['participant_a']===$match['participant_a'] && $saved['field_slots_snapshot_json']===$match['field_slots_snapshot_json'],'Alteracao do perfil mudou nome ou campo da partida');
    trackingCheck(!str_contains(trackingRoute($routes.'competicoes/index.php'),'<h3>Treino</h3>'),'Opcao de novo treino aparece com um time');
    $count = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
    trackingCheck(str_contains(trackingRoute($routes.'partidas/store.php',$game),'Ative os dois times'),'Criou treino com um time');
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn()===$count,'Bloqueio deixou partida parcial');
    trackingCheck(str_contains(trackingRoute($routes.'partidas/create.php',[],['competition_id'=>$trainingId]),'Ative os dois times'),'Formulario permitiu treino sem dois times');
    trackingCheck(str_contains(trackingRoute($routes.'competicoes/view.php',[],['id'=>$trainingId]),'Treinos de outubro'),'Historico ficou inacessivel sem segundo time');
    trackingRoute($routes.'classificacao/store.php',['_csrf'=>'test','competition_id'=>$trainingId,'active_fields'=>['position','name','played','wins','draws','losses','points'],'sort_field'=>'points','sort_direction'=>'desc']);
    trackingRoute($routes.'competicoes/result_store.php',['_csrf'=>'test','match_id'=>$matchId,'score_a'=>2,'score_b'=>1],['id'=>$trainingId]);
    $rows = $pdo->query('SELECT data_json FROM classification_rows')->fetchAll(PDO::FETCH_COLUMN);
    $points = []; foreach ($rows as $row) { $data=json_decode($row,true); $points[(int)$data['player_id']]=$data['points']; }
    trackingCheck(count($points)===4 && $points[1]==3 && $points[2]==3 && $points[3]==0 && $points[4]==0,'Classificacao individual nao segue resultado da equipe na partida');
    classificationRebuildInternalCompetition($pdo,$trainingId);
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM classification_rows')->fetchColumn()===4,'Recalculo duplicou classificacao');
    foreach ([[2,0],[1,1]] as $scores) {
        trackingRoute($routes.'partidas/store.php',[...$game,'competition_id'=>$champId,'participant_a'=>'Adversario 1','participant_b'=>'Visitante','lineup_mode'=>'team_roster']);
        $id = (int)$pdo->query('SELECT MAX(id) FROM matches')->fetchColumn();
        trackingCheck($pdo->query('SELECT participant_a FROM matches WHERE id='.$id)->fetchColumn()==='Sabia FC','Criou jogo entre adversarios');
        trackingRoute($routes.'competicoes/result_store.php',['_csrf'=>'test','match_id'=>$id,'score_a'=>$scores[0],'score_b'=>$scores[1]],['id'=>$champId]);
    }
    trackingRoute($routes.'competicoes/update.php',[...$champ,'name'=>'Campeonato 2026','official_position'=>'4'],['id'=>$champId]);
    trackingCheck((int)$pdo->query('SELECT official_position FROM competitions WHERE id='.$champId)->fetchColumn()===4,'Nao atualizou posicao com partidas existentes');
    trackingRoute($routes.'competicoes/update.php',[...$champ,'official_position'=>'-1'],['id'=>$champId]);
    trackingCheck((int)$pdo->query('SELECT official_position FROM competitions WHERE id='.$champId)->fetchColumn()===4,'Aceitou posicao invalida');
    $champView = trackingRoute($routes.'competicoes/view.php',[],['id'=>$champId]);
    trackingCheck(str_contains($champView,'Pontos do meu time') && str_contains($champView,'4º'),'Resumo do campeonato nao exibiu pontos e posicao');
    $trainingView = trackingRoute($routes.'competicoes/view.php',[],['id'=>$trainingId]);
    trackingCheck(str_contains($trainingView,'Ver Classificacao'),'Classificacao do treino nao esta disponivel');
    $pdo->exec("UPDATE team_profile SET formations_count=2,team_type='custom',custom_slots_json='{\"GO\":1,\"CA1\":1}' WHERE id=1");
    $pdo->exec("CREATE TRIGGER fail_tracking_snapshot BEFORE INSERT ON match_lineup FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test rollback'");
    $count = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
    trackingRoute($routes.'partidas/store.php',$game);
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn()===$count,'Falha na escala deixou partida parcial');
    $pdo->exec('DROP TRIGGER fail_tracking_snapshot');
    $pdo->exec("UPDATE team_roster SET status='inactive'");
    trackingRoute($routes.'partidas/store.php',$game);
    $emptyId = (int)$pdo->query('SELECT MAX(id) FROM matches')->fetchColumn();
    trackingCheck($pdo->query('SELECT lineup_snapshot_at FROM matches WHERE id='.$emptyId)->fetchColumn()!==null,'Escala vazia nao foi congelada');
    $pdo->exec("UPDATE team_roster SET status='active' WHERE player_id=1");
    matchLineupSyncTrainingSnapshot($pdo,$emptyId);
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM match_lineup WHERE match_id='.$emptyId)->fetchColumn()===0,'Escala vazia foi preenchida com elenco posterior');
    trackingCheck(str_contains(trackingRoute($routes.'treino/index.php'),'admin/meu_time/index.php'),'Link antigo nao redireciona');
    trackingCheck(str_contains(trackingRoute($routes.'treino/store.php',['_csrf'=>'test']),'admin/meu_time/index.php'),'Acao antiga ainda altera treino');
    trackingCheck((int)$pdo->query('SELECT player_id FROM training_roster WHERE id=42')->fetchColumn()===5 && $pdo->query('SELECT custom_slots_json FROM training_settings WHERE id=42')->fetchColumn()==='legacy','Dados legados foram alterados');
    $retired = json_decode(file_get_contents($root.'/modules/treino/module.json'),true);
    trackingCheck($retired['menu']===null && !isset($retired['database']['schema']),'Treino antigo continua ativo');
    if (!is_dir($root.'/_deploy')) mkdir($root.'/_deploy',0700,true);
    trackingCheck(matchLineupPositionDisplay('VOL2') === 'VOL' && matchLineupPositionDisplay(null) === '-', 'Exibicao das siglas indisponivel');
    trackingCheck(matchLineupInitials('Moises Cavalcante') === 'MC', 'Iniciais indisponiveis');
    trackingCheck(str_contains(matchLineupAvatar(null, 'Moises Cavalcante', 'avatar'), '>MC</span>'), 'Avatar sem foto falhou');
    trackingCheck(str_contains(matchLineupAvatar('foto".png', '<Nome>', 'avatar'), '&quot;.png') && str_contains(matchLineupAvatar(null, '<Nome>', 'avatar'), '&lt;'), 'Avatar nao escapa dados');
    // Friendly games copy the chosen field slot, not the player's primary position.
    $pdo->exec("UPDATE team_profile SET team_type='custom',custom_slots_json='{\"GO\":1,\"CA1\":1,\"MAT1\":1}',starters_count=3 WHERE id=1; UPDATE team_roster SET status='inactive'");
    $pdo->exec("UPDATE team_roster SET status='active',team_key='time_1',slot_group='goleiro',slot_index=0,field_position_code='GO' WHERE player_id=1; UPDATE team_roster SET status='active',team_key='time_1',slot_group='atacante',slot_index=0,field_position_code='CA1' WHERE player_id=2");
    $pdo->exec("UPDATE team_roster SET status='active' WHERE player_id IN (3,4)");
    $winger = (int)$pdo->query("SELECT id FROM player_positions WHERE code='PTD1'")->fetchColumn();
    $pdo->prepare('UPDATE players SET position_id=? WHERE id=2')->execute([$winger]);
    file_put_contents($fixture.'/test-plan', 'Plano Gratis');
    $beforeTraining = (int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn();
    trackingCheck(str_contains(trackingRoute($routes.'partidas/store.php',$game),'Ative os dois times'), 'Gratis nao bloqueou treino pelo plano');
    trackingCheck((int)$pdo->query('SELECT COUNT(*) FROM matches')->fetchColumn()===$beforeTraining, 'Gratis criou treino usando segundo time antigo');
    trackingRoute($routes.'competicoes/store.php',['_csrf'=>'test','name'=>'Amistosos 2026','context'=>'external','type'=>'friendly','status'=>'active']);
    $friendlyId = (int)$pdo->query("SELECT id FROM competitions WHERE name='Amistosos 2026'")->fetchColumn();
    $friendlyCreate = trackingRoute($routes.'partidas/create.php',[],['competition_id'=>$friendlyId]);
    trackingCheck(!preg_match('/<select[^>]*name="lineup_mode"/', $friendlyCreate) && str_contains($friendlyCreate,'value="team_roster"'), 'Amistoso permite selecionar modo');
    trackingRoute($routes.'partidas/store.php',[...$game,'competition_id'=>$friendlyId,'participant_b'=>'Visitante','lineup_mode'=>'arrival_order']);
    $friendlyMatchId = (int)$pdo->query('SELECT MAX(id) FROM matches')->fetchColumn();
    trackingCheck($pdo->query('SELECT lineup_mode FROM matches WHERE id='.$friendlyMatchId)->fetchColumn()==='team_roster', 'POST direto mudou modo do amistoso');
    $friendlyRows = $pdo->query('SELECT * FROM match_lineup WHERE match_id='.$friendlyMatchId.' ORDER BY player_id')->fetchAll(PDO::FETCH_ASSOC);
    trackingCheck(array_map('intval',array_column($friendlyRows,'player_id'))===[1,2], 'Amistoso incluiu jogadores do segundo time');
    $chosen = array_values(array_filter($friendlyRows, static fn($row) => (int)$row['player_id'] === 2));
    trackingCheck(count($chosen)===1 && $chosen[0]['status']==='starter' && $chosen[0]['slot_group']==='atacante' && (int)$chosen[0]['slot_index']===0, 'Amistoso nao copiou posicao escolhida');
    trackingCheck((int)$pdo->query('SELECT position_id FROM players WHERE id=2')->fetchColumn()===$winger, 'Amistoso alterou posicao principal');
    trackingCheck(str_contains(trackingRoute($routes.'partidas/show.php',[],['id'=>$friendlyMatchId],2),'Acesso negado'), 'Cliente acessou tela administrativa');
    trackingCheck(str_contains(trackingRoute($routes.'partidas/show.php',[],['id'=>999999]),'Partida nao encontrada'), 'Partida inexistente nao tratada');
    $friendlyView = trackingRoute($routes.'partidas/show.php',[],['id'=>$friendlyMatchId]);
    trackingCheck(str_contains($friendlyView,'Atleta 2') && str_contains($friendlyView,'<small>CA</small>') && !str_contains($friendlyView,'<small>PTD</small>'), 'Campo exibe a principal em vez da posicao escalada');
    trackingCheck((bool)preg_match('/>\s*MAT\s*<\/span>/', $friendlyView), 'Vaga vazia personalizada nao exibiu MAT');
    $friendlyEdit = trackingRoute($routes.'partidas/edit.php',[],['id'=>$friendlyMatchId]);
    trackingCheck(!preg_match('/<select[^>]*name="lineup_mode"/', $friendlyEdit), 'Edicao permite mudar modo do amistoso');
    trackingRoute($routes.'partidas/update.php', ['_csrf'=>'test','participant_a'=>'Indevido','participant_b'=>'Visitante','status'=>'scheduled','lineup_mode'=>'arrival_order','match_date'=>'2026-11-01T10:00'], ['id'=>$friendlyMatchId]);
    trackingCheck($pdo->query('SELECT lineup_mode FROM matches WHERE id='.$friendlyMatchId)->fetchColumn()==='team_roster', 'Edicao direta mudou modo do amistoso');
    $friendlyStats = trackingRoute($routes.'competicoes/view.php',[],['id'=>$friendlyId]);
    trackingCheck(!str_contains($friendlyStats,'Pontos do meu time') && str_contains($friendlyStats,'Desempenho') && str_contains($friendlyStats,'Gols'), 'Resumo do amistoso exibe pontos ou perdeu estatisticas');
    file_put_contents($root.'/_deploy/football-friendly-create-preview.html',$friendlyCreate);
    file_put_contents($root.'/_deploy/football-friendly-edit-preview.html',$friendlyEdit);
    file_put_contents($root.'/_deploy/football-friendly-stats-preview.html',$friendlyStats);
    // A later change to Meu Time or to the registration must not rewrite a match.
    $pdo->exec("UPDATE team_profile SET custom_slots_json='{\"GO\":1}' WHERE id=1; UPDATE team_roster SET status='inactive' WHERE player_id=2; UPDATE players SET position_id=(SELECT id FROM player_positions WHERE code='GO1' LIMIT 1) WHERE id=2");
    $friendlyView = trackingRoute($routes.'partidas/show.php',[],['id'=>$friendlyMatchId]);
    $friendlyLineup = trackingRoute($routes.'partidas/lineup.php',[],['id'=>$friendlyMatchId]);
    trackingCheck(str_contains($friendlyView,'<small>CA</small>') && str_contains($friendlyLineup,'<small>CA</small>'), 'Mudanca posterior alterou sigla da partida');
    trackingCheck((bool)preg_match('/>\s*MAT\s*<\/span>/', $friendlyLineup), 'Escalacao trocou a sigla da vaga vazia');
    trackingCheck($friendlyRows===$pdo->query('SELECT * FROM match_lineup WHERE match_id='.$friendlyMatchId.' ORDER BY player_id')->fetchAll(PDO::FETCH_ASSOC), 'Abrir partida reescreveu escalacao');
    $pdo->exec("UPDATE matches SET status='finished' WHERE id=$friendlyMatchId");
    trackingCheck(str_contains(trackingRoute($routes.'partidas/show.php',[],['id'=>$friendlyMatchId]),'<small>CA</small>'), 'Historico finalizado perdeu posicao de campo');
    file_put_contents($root.'/_deploy/football-friendly-preview.html',$friendlyView);
    file_put_contents($root.'/_deploy/football-friendly-lineup-preview.html',$friendlyLineup);
    file_put_contents($fixture.'/test-plan', 'Plano Start');
    foreach (['championship'=>$champView,'training'=>$trainingView,'tracking-edit'=>trackingRoute($routes.'competicoes/edit.php',[],['id'=>$champId]),'tracking-index'=>trackingRoute($routes.'competicoes/index.php')] as $key=>$html) file_put_contents($root.'/_deploy/football-'.$key.'-preview.html',$html);
    echo "PASS: amistoso com posicao alternativa, Ver Partida/Escalacao sem fatal, vagas vazias, avatares, snapshots preservados, campeonato, treino, classificacao 3/1/0, rollback e permissoes.\n";
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$database.'`');
    if (is_dir($fixture)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());
        rmdir($fixture);
    }
}
