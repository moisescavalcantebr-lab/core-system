<?php
declare(strict_types=1);

function ensureSyncDirectory(string $directory): void
{
    if (is_dir($directory)) return;
    if (!@mkdir($directory,0755,true) && !is_dir($directory)) throw new RuntimeException('Nao foi possivel criar a pasta: '.$directory);
}

function writeModuleFile(string $destinationFile,string $content): bool
{
    if (is_file($destinationFile) && @file_get_contents($destinationFile)===$content) return false;
    $directory=dirname($destinationFile);
    ensureSyncDirectory($directory);
    if (!is_writable($directory)) throw new RuntimeException('Sem permissao para substituir o arquivo: '.$destinationFile);
    $temporary=@tempnam($directory,'.module-sync-');
    if ($temporary===false) throw new RuntimeException('Nao foi possivel preparar a copia: '.$destinationFile);
    try {
        if (realpath(dirname($temporary))!==realpath($directory)) throw new RuntimeException('Sem permissao para substituir o arquivo: '.$destinationFile);
        if (@file_put_contents($temporary,$content)!==strlen($content) || !@chmod($temporary,0644)) throw new RuntimeException('Falha ao preparar arquivo: '.$destinationFile);
        // Keep the original intact until a complete replacement is ready in the same directory.
        if (!@rename($temporary,$destinationFile)) throw new RuntimeException('Falha ao substituir arquivo: '.$destinationFile);
    } finally { if (is_file($temporary)) @unlink($temporary); }
    return true;
}

function copyModuleFile(string $sourceFile,string $destinationFile): bool
{
    $content=@file_get_contents($sourceFile);
    if ($content===false) throw new RuntimeException('Nao foi possivel ler o arquivo: '.$sourceFile);
    return writeModuleFile($destinationFile,$content);
}

function syncModuleFolder(string $source,string $destination): void
{
    if (!is_dir($source)) return;
    ensureSyncDirectory($destination);
    foreach (scandir($source) ?: [] as $file) {
        if ($file==='.' || $file==='..') continue;
        if (is_dir($source.'/'.$file)) syncModuleFolder($source.'/'.$file,$destination.'/'.$file);
        else copyModuleFile($source.'/'.$file,$destination.'/'.$file);
    }
}

function moduleSyncManifest(string $path): array
{
    $json=@file_get_contents($path);
    if ($json===false) throw new RuntimeException('Nao foi possivel ler o manifesto: '.$path);
    $manifest=json_decode($json,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($manifest) || !is_string($manifest['name'] ?? null)) throw new RuntimeException('Manifesto invalido: '.$path);
    return $manifest;
}

function syncRefreshInstalledBaseModules(string $basePath): void
{
    $installedPath=$basePath.'/modules'; $catalogPath=ROOT_PATH.'/modules';
    if (!is_dir($installedPath) || !is_dir($catalogPath)) return;
    foreach (scandir($installedPath) ?: [] as $slug) {
        if ($slug==='.' || $slug==='..') continue;
        $source=$catalogPath.'/'.$slug; $destination=$installedPath.'/'.$slug;
        if (!is_file($source.'/module.json') || !is_file($destination.'/module.json')) continue;
        $catalog=moduleSyncManifest($source.'/module.json');
        $installed=moduleSyncManifest($destination.'/module.json');
        $manifest=$catalog+$installed;
        if (array_key_exists('participant_access',$installed)) $manifest['participant_access']=$installed['participant_access'];
        syncRefreshModuleFiles($source,$destination,true);
        if ($manifest!==$installed) {
            if ($manifest===$catalog) copyModuleFile($source.'/module.json',$destination.'/module.json');
            else writeModuleFile($destination.'/module.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
        }
        foreach (($manifest['copy'] ?? []) as $copy) {
            if (!is_array($copy)) continue;
            $from=trim((string)($copy['from'] ?? ''),'/'); $to=trim((string)($copy['to'] ?? ''),'/');
            if ($from==='' || $to==='' || str_contains($from,'..') || str_contains($to,'..')) continue;
            if (is_dir($source.'/'.$from)) syncRefreshModuleFiles($source.'/'.$from,$basePath.'/'.$to);
            elseif (is_file($source.'/'.$from)) copyModuleFile($source.'/'.$from,$basePath.'/'.$to);
        }
    }
}

function syncRefreshModuleFiles(string $source,string $destination,bool $skipRootManifest=false): void
{
    if (!is_dir($source)) return;
    ensureSyncDirectory($destination);
    foreach (scandir($source) ?: [] as $file) {
        if ($file==='.' || $file==='..' || ($skipRootManifest && $file==='module.json')) continue;
        $sourceFile=$source.'/'.$file; $destinationFile=$destination.'/'.$file;
        if (str_ends_with(str_replace('\\','/',$sourceFile),'/database/schema.sql')) continue;
        if (is_dir($sourceFile)) syncRefreshModuleFiles($sourceFile,$destinationFile);
        else copyModuleFile($sourceFile,$destinationFile);
    }
}
