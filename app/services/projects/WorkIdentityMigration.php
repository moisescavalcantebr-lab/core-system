<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/helpers/base_identity.php';

final class WorkIdentityMigration
{
    private string $root;

    public function __construct(private PDO $core, string $root)
    {
        $resolved = realpath($root);
        if (!$resolved) throw new RuntimeException('Workspace nao encontrado.');
        $this->root = str_replace('\\','/',$resolved);
    }

    private function path(string $relative): string
    {
        if (str_contains($relative,'..') || str_contains($relative,'\\') || str_starts_with($relative,'/')) throw new RuntimeException('Caminho de migracao invalido.');
        $path = $this->root.'/'.$relative;
        $real = realpath($path);
        if ($real && !str_starts_with(str_replace('\\','/',$real),$this->root.'/')) throw new RuntimeException('Caminho fora do workspace.');
        if (is_link($path)) throw new RuntimeException('Link simbolico nao permitido na migracao.');
        return $path;
    }

    private function copy(string $source, string $target): void
    {
        if (is_link($source)) throw new RuntimeException('Link simbolico em arquivos do projeto.');
        if (is_dir($source)) {
            if (!is_dir($target) && !mkdir($target,0755,true)) throw new RuntimeException('Nao foi possivel preparar destino.');
            foreach (scandir($source) ?: [] as $name) if ($name !== '.' && $name !== '..') $this->copy($source.'/'.$name,$target.'/'.$name);
        } elseif (is_file($source)) {
            if (!is_dir(dirname($target))) mkdir(dirname($target),0755,true);
            if (!copy($source,$target)) throw new RuntimeException('Nao foi possivel copiar arquivo.');
        }
    }

    private function json(string $path, array $data, bool $private = false): void
    {
        $encoded = json_encode($data,JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path,$encoded."\n",LOCK_EX) === false) throw new RuntimeException('Nao foi possivel salvar metadados.');
        if ($private) chmod($path,0600);
    }

    public function run(bool $apply = false): array
    {
        $key = 'work-identity-'.substr(hash('sha256',(string)$this->core->query('SELECT DATABASE()')->fetchColumn()),0,32);
        $lock = $this->core->prepare('SELECT GET_LOCK(?,10)');
        $lock->execute([$key]);
        if ((int)$lock->fetchColumn() !== 1) throw new RuntimeException('Outra migracao de identidade esta em andamento.');
        try {
            return $this->migrate($apply);
        } finally {
            $release = $this->core->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$key]);
        }
    }

    private function migrate(bool $apply): array
    {
        $basePath = $this->path('bases/meu-trabalho');
        if (!is_file($basePath.'/base.json')) throw new RuntimeException('Base Meu Trabalho ausente.');
        $bases = $this->core->query("SELECT * FROM bases WHERE slug IN ('meu-holerite','meu-trabalho')")->fetchAll(PDO::FETCH_ASSOC);
        if (count($bases) > 1) throw new RuntimeException('Bases antiga e nova coexistem. Nenhum dado sera mesclado automaticamente.');
        $base = $bases[0] ?? null;
        $project = null;
        $projectDb = null;
        $source = null;
        $config = null;
        $resume = null;
        if ($base) {
            $stmt = $this->core->prepare("SELECT * FROM projects WHERE base_id=? AND status<>'deleted' ORDER BY id");
            $stmt->execute([(int)$base['id']]);
            $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($projects) > 1) throw new RuntimeException('Mais de um projeto nesta base. Defina o projeto antes de renomear.');
            $project = $projects[0] ?? null;
            $prices = $this->core->prepare("SELECT * FROM base_module_prices WHERE base_id=? AND module_slug IN ('equatorial','leituras')");
            $prices->execute([(int)$base['id']]);
            $priceRows = $prices->fetchAll(PDO::FETCH_ASSOC);
            if (count($priceRows) > 1) throw new RuntimeException('Cadastros comerciais de modulo em conflito.');
        }
        $target = $this->path('projects/meu-trabalho');
        if ($project) {
            $slug = (string)$project['slug'];
            if (!preg_match('/^[a-z0-9][a-z0-9-]*$/D',$slug) || ltrim((string)$project['path'],'/') !== 'projects/'.$slug) throw new RuntimeException('Caminho cadastrado do projeto inesperado.');
            $source = $this->path('projects/'.$slug);
            $conflict = $this->core->prepare("SELECT id FROM projects WHERE slug='meu-trabalho' AND id<>?");
            $conflict->execute([(int)$project['id']]);
            if ($conflict->fetchColumn()) throw new RuntimeException('Slug meu-trabalho ocupado por outro projeto.');
            if ($source !== $target && is_dir($target)) {
                $targetMeta = json_decode((string)@file_get_contents($target.'/project.json'),true);
                $resume = is_file($target.'/.identity-migration.json') ? json_decode((string)file_get_contents($target.'/.identity-migration.json'),true) : null;
                if ((int)($targetMeta['id'] ?? 0) !== (int)$project['id'] || (!is_file($source.'/.identity-alias') && (int)($resume['project_id'] ?? 0) !== (int)$project['id'])) throw new RuntimeException('Pasta meu-trabalho ja existe. Nenhuma pasta sera sobrescrita.');
                $source = $target;
            }
            if (!is_dir($source) || !is_file($source.'/app/config/database.php')) throw new RuntimeException('Projeto cadastrado nao esta instalado neste workspace.');
            if (is_file($source.'/.identity-migration.json')) {
                $resume = json_decode((string)file_get_contents($source.'/.identity-migration.json'),true,512,JSON_THROW_ON_ERROR);
                if ((int)($resume['project_id'] ?? 0) !== (int)$project['id']) throw new RuntimeException('Marcador de migracao pertence a outro projeto.');
            }
            $config = require $source.'/app/config/database.php';
            $projectDb = new PDO('mysql:host='.$config['host'].';dbname='.$config['name'].';charset='.($config['charset'] ?? 'utf8mb4'),$config['user'],$config['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            require_once $this->path('modules/leituras/web/admin/leituras/helpers.php');
            eqLegacyTablePlan($projectDb);
        }
        $actions = ['Base: meu-holerite -> meu-trabalho; ID e vinculos preservados.'];
        if ($project) $actions[] = 'Projeto #'.$project['id'].': '.$project['slug'].' -> meu-trabalho; banco atual preservado.';
        else $actions[] = 'Nenhum projeto desta base instalado neste ambiente.';
        if (!$apply) return $actions;
        $backup = $this->path('_backups/work-identity-'.date('Ymd-His').'-'.bin2hex(random_bytes(3)));
        if (!mkdir($backup,0700,true)) throw new RuntimeException('Nao foi possivel preparar backup.');
        chmod($backup,0700);
        file_put_contents($backup.'/.htaccess',"Require all denied\n");
        $this->json($backup.'/core-metadata.json',['base'=>$base,'project'=>$project,'module_prices'=>$priceRows ?? []],true);
        $maintenance = null;
        $previousHtaccess = null;
        if ($project && $source && $projectDb) {
            $previousHtaccess = is_file($source.'/.htaccess') ? file_get_contents($source.'/.htaccess') : null;
            if ($resume) $previousHtaccess = $resume['htaccess'] ?? null;
            foreach (['project.json','.htaccess','app/database/schema.sql','modules/equatorial','modules/leituras','web/admin/equatorial','web/admin/leituras'] as $file) $this->copy($source.'/'.$file,$backup.'/project/'.$file);
            $tables = $projectDb->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            $data = [];
            foreach (['equatorial','leituras'] as $prefix) foreach (['reasons','readings','workspaces','periods'] as $suffix) {
                $table = $prefix.'_'.$suffix;
                if (in_array($table,$tables,true)) $data[$table] = ['schema'=>$projectDb->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],'rows'=>$projectDb->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)];
            }
            $this->json($backup.'/reading-tables.json',$data,true);
            $maintenance = $source.'/.htaccess';
            $this->json($source.'/.identity-migration.json',['project_id'=>(int)$project['id'],'old_slug'=>$resume['old_slug'] ?? $project['slug'],'htaccess'=>$previousHtaccess],true);
            if (file_put_contents($maintenance,"RewriteEngine On\nRewriteRule ^ - [R=503,L]\n",LOCK_EX) === false) throw new RuntimeException('Nao foi possivel ativar manutencao do projeto.');
            if ($source !== $target) {
                if (!rename($source,$target)) throw new RuntimeException('Nao foi possivel renomear a pasta do projeto.');
                $source = $target;
                $maintenance = $target.'/.htaccess';
            }
            $hasReadings = is_dir($target.'/modules/equatorial') || is_dir($target.'/modules/leituras') || isset($data['equatorial_readings']) || isset($data['leituras_readings']);
            if ($hasReadings) {
                eqEnsureSchema($projectDb);
                if (is_dir($target.'/modules/equatorial') && !rename($target.'/modules/equatorial',$backup.'/legacy-module')) throw new RuntimeException('Nao foi possivel arquivar o modulo antigo.');
                $this->copy($basePath.'/modules/leituras',$target.'/modules/leituras');
                $this->copy($basePath.'/web/admin/leituras',$target.'/web/admin/leituras');
                if (!is_dir($target.'/web/admin/equatorial')) mkdir($target.'/web/admin/equatorial',0755,true);
                if (file_put_contents($target.'/web/admin/equatorial/index.php',"<?php\nrequire dirname(__DIR__).'/leituras/index.php';\n",LOCK_EX) === false) throw new RuntimeException('Nao foi possivel preservar a entrada antiga do modulo.');
                $schemaPath = $target.'/app/database/schema.sql';
                if (is_file($schemaPath)) {
                    $schema = str_replace(['equatorial_','EQUATORIAL_'],['leituras_','LEITURAS_'],(string)file_get_contents($schemaPath));
                    if (file_put_contents($schemaPath,$schema,LOCK_EX) === false) throw new RuntimeException('Nao foi possivel atualizar o esquema do projeto.');
                }
            }
            $metaPath = $target.'/project.json';
            $meta = is_file($metaPath) ? json_decode((string)file_get_contents($metaPath),true,512,JSON_THROW_ON_ERROR) : [];
            $meta['id'] = (int)$project['id'];
            $meta['name'] = 'Meu Trabalho';
            $meta['slug'] = 'meu-trabalho';
            if (isset($meta['base_slug'])) $meta['base_slug'] = 'meu-trabalho';
            if (isset($meta['modules']) && is_array($meta['modules'])) $meta['modules'] = array_values(array_unique(array_map(static fn($slug)=>$slug === 'equatorial' ? 'leituras' : $slug,$meta['modules'])));
            $this->json($metaPath,$meta);
            $settings = $projectDb->query("SHOW TABLES LIKE 'project_settings'")->fetchColumn();
            if ($settings) {
                $stmt = $projectDb->prepare("UPDATE project_settings SET setting_value=? WHERE setting_key='site_name'");
                $stmt->execute(['Meu Trabalho']);
                $projectDb->exec("UPDATE project_settings SET setting_value='leituras' WHERE setting_key='dashboard_default_group' AND setting_value='equatorial'");
            }
        }
        $this->core->beginTransaction();
        try {
            $id = baseRenameIdentity($this->core,'meu-holerite','meu-trabalho','Meu Trabalho');
            if ($id) {
                $stmt = $this->core->prepare("UPDATE base_module_prices SET module_slug='leituras' WHERE base_id=? AND module_slug='equatorial'");
                $stmt->execute([$id]);
            }
            if ($project) {
                $stmt = $this->core->prepare("UPDATE projects SET name='Meu Trabalho',slug='meu-trabalho',path='/projects/meu-trabalho' WHERE id=?");
                $stmt->execute([(int)$project['id']]);
            }
            $this->core->commit();
        } catch (Throwable $e) {
            if ($this->core->inTransaction()) $this->core->rollBack();
            throw $e;
        }
        $oldSlug = $resume['old_slug'] ?? ($project['slug'] ?? null);
        if ($project && $oldSlug !== 'meu-trabalho') {
            $alias = $this->path('projects/'.$oldSlug);
            if (!is_dir($alias.'/web')) mkdir($alias.'/web',0755,true);
            if (file_put_contents($alias.'/.identity-alias','meu-trabalho',LOCK_EX) === false || file_put_contents($alias.'/web/.htaccess',"RewriteEngine On\nRewriteRule ^(.*)$ /projects/meu-trabalho/web/\$1 [R=308,L,NE]\n",LOCK_EX) === false) throw new RuntimeException('Nao foi possivel preservar o endereco antigo.');
        }
        if ($maintenance) {
            if ($previousHtaccess === null) {
                if (!unlink($maintenance)) throw new RuntimeException('Nao foi possivel encerrar manutencao.');
            } elseif (file_put_contents($maintenance,$previousHtaccess,LOCK_EX) === false) throw new RuntimeException('Nao foi possivel restaurar configuracao de acesso.');
            unlink($target.'/.identity-migration.json');
        }
        $oldBase = $this->path('bases/meu-holerite');
        if (is_dir($oldBase) && !rename($oldBase,$backup.'/legacy-base')) throw new RuntimeException('Base antiga preservada, mas nao foi possivel arquivar a pasta.');
        $actions[] = 'Migracao concluida. Backup privado: '.basename($backup);
        return $actions;
    }
}
