<?php
declare(strict_types=1);
// CLI only. Credentials stay in the existing server configuration.
if(PHP_SAPI!=='cli')exit(1);ini_set('display_errors','0');
try{
 $root='/var/www/html';$config=require $root.'/env/env.production.php';$d=$config['db'];
 $core=new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $paths=$core->query("SELECT p.path FROM projects p JOIN bases b ON b.id=p.base_id WHERE b.slug='futebol' ORDER BY p.id")->fetchAll(PDO::FETCH_COLUMN);
 $paths=array_values(array_unique(array_map(static fn($p)=>ltrim($p,'/'),$paths)));
 foreach($paths as $path)if(!preg_match('~^projects/[a-zA-Z0-9_-]+$~D',$path)||!is_dir($root.'/'.$path))throw new RuntimeException('project_path');
 $mode=$argv[1]??'';$selected=$argv[2]??'';$inventory=[];
 foreach($paths as $path){
  if($mode!=='--inventory'&&$path!==$selected)continue;
  $file=$root.'/'.$path.'/app/config/database.php';if(is_link($file))throw new RuntimeException('config_link');$db=require $file;
  if(!preg_match('/^[a-zA-Z0-9_]+$/D',$db['name']??'')||$db['name']===$d['name']||$db['host']!==$d['host'])throw new RuntimeException('project_database');
  $pdo=new PDO('mysql:host='.$db['host'].';dbname='.$db['name'].';charset=utf8mb4',$db['user'],$db['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $tables=$pdo->query('SHOW FULL TABLES WHERE Table_type="BASE TABLE"')->fetchAll(PDO::FETCH_COLUMN);
  foreach(['finance_entries','finance_categories','players','project_users'] as $required)if(!in_array($required,$tables,true))throw new RuntimeException('missing_parent');
  if($mode==='--inventory'){$inventory[]=['path'=>$path,'database'=>$db['name'],'tables'=>count($tables),'entries'=>(int)$pdo->query('SELECT COUNT(*) FROM finance_entries')->fetchColumn()];continue;}
  if($mode==='--snapshot'){
   $digest=[];sort($tables);$pdo->beginTransaction();
   foreach($tables as $table){if(!preg_match('/^finance_(?!team_)[a-z0-9_]+$/D',$table))continue;$rows=[];foreach($pdo->query('SELECT * FROM `'.$table.'`',PDO::FETCH_ASSOC) as $row)$rows[]=hash('sha256',serialize($row));sort($rows);$digest[$table]=hash('sha256',implode('',$rows));}
   $pdo->commit();echo hash('sha256',serialize($digest)),PHP_EOL;exit;
  }
  if($mode==='--migrate'){
   // Only the addon schema is applied. The complete base schema is never imported into an existing project.
   $schema=stream_get_contents(STDIN);if(!$schema||strlen($schema)>100000)throw new RuntimeException('schema_size');
   $pdo->exec($schema);
   foreach(['finance_team_history','finance_team_charges','finance_team_credit_movements','finance_team_mutex'] as $table)$pdo->query('SELECT 1 FROM '.$table.' LIMIT 1');
   echo 'finance_additive_schema=PASS',PHP_EOL;exit;
  }
  throw new RuntimeException('mode');
 }
 if($mode!=='--inventory')throw new RuntimeException('project_not_found');
 if(count(array_unique(array_column($inventory,'database')))!==count($inventory))throw new RuntimeException('shared_project_database');
 echo json_encode($inventory,JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'finance_migration_failed='.(get_class($e)===RuntimeException::class?$e->getMessage():'private_details_omitted').PHP_EOL);exit(1);}
