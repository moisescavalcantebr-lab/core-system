<?php
declare(strict_types=1);
ini_set('display_errors','0');
try {
    if (PHP_SAPI !== 'cli') exit(1);
    if (($argv[1] ?? '') === '--projects') {
        $config = require '/var/www/html/env/env.production.php';
        $d = $config['db'];
        $pdo = new PDO('mysql:host='.$d['host'].';dbname='.$d['name'].';charset=utf8mb4',$d['user'],$d['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
        $rows = $pdo->query("SELECT p.path FROM projects p JOIN bases b ON b.id=p.base_id WHERE b.slug='futebol' ORDER BY p.id")->fetchAll(PDO::FETCH_COLUMN);
        $rows = array_map(static fn(string $path):string=>ltrim($path,'/'),$rows);
        echo json_encode(array_values(array_unique($rows)),JSON_THROW_ON_ERROR);
        exit;
    }
    [$script,$stage,$root,$mode] = $argv;
    if (!in_array($mode,['--preflight','--after'],true)) exit(1);
    $manifest = json_decode(file_get_contents($stage.'/manifest.json'),true,32,JSON_THROW_ON_ERROR);
    $projects = json_decode(file_get_contents($stage.'/projects.json'),true,16,JSON_THROW_ON_ERROR);
    $entries = [];
    foreach ($projects as $path) {
        if (!preg_match('~^projects/[a-zA-Z0-9_-]+$~D',$path)) throw new RuntimeException('project_path_format:'.preg_replace('~[^a-zA-Z0-9_./-]~','?',substr($path,0,160)));
        if (!is_dir($root.'/'.$path)) throw new RuntimeException('project_directory_missing:'.$path);
    }
    foreach ($manifest['files'] as $file) {
        $path = $file['path'];
        if (!preg_match('~^(?:bases/futebol/(?:app/(?:bootstrap/project_bootstrap|helpers/football_plan|views/partials/sidebar_admin)|web/admin/(?:dashboard|(?:competicoes|meu_time)/[a-z_]+)|modules/(?:competicoes|meu_time)/web/admin/(?:competicoes|meu_time)/[a-z_]+)|modules/(?:competicoes|meu_time)/web/admin/(?:competicoes|meu_time)/[a-z_]+)\.php$~D',$path)) throw new RuntimeException('source_path');
        $targets = [$path];
        if (str_starts_with($path,'bases/futebol/')) {
            $relative = substr($path,strlen('bases/futebol/'));
            foreach ($projects as $project) {
                if (str_starts_with($relative,'modules/') && !is_dir($root.'/'.$project.'/modules/'.explode('/',$relative)[1])) continue;
                if (str_starts_with($relative,'web/admin/competicoes/') && !is_dir($root.'/'.$project.'/web/admin/competicoes')) continue;
                if (str_starts_with($relative,'web/admin/meu_time/') && !is_dir($root.'/'.$project.'/web/admin/meu_time')) continue;
                $targets[] = $project.'/'.$relative;
            }
        }
        $hash = static fn(string $p):string => hash('sha256',str_replace("\r\n","\n",file_get_contents($p)));
        if ($hash($stage.'/'.$path) !== $file['sha256']) throw new RuntimeException('package_hash');
        foreach ($targets as $target) {
            foreach ([$root.'/'.$target,$stage.'/'.$path] as $candidate) {
                $part='';foreach(explode('/',$candidate) as $component){$part.='/'.$component;if(is_link($part))throw new RuntimeException('symlink');}
            }
            if (!is_file($root.'/'.$target)) {
                if ($mode==='--after' || !$file['allow_missing']) throw new RuntimeException('missing:'.$target);
            } elseif (!in_array($hash($root.'/'.$target),$mode==='--after'?[$file['sha256']]:$file['allowed'],true)) {
                throw new RuntimeException('file_conflict:'.$target);
            }
            $entries[$target]=$path;
        }
    }
    foreach ($entries as $target=>$source) echo $source,"\t",$target,PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,'football_check_failed='.($e instanceof RuntimeException?$e->getMessage():'private_details_omitted').PHP_EOL);exit(1);
}
