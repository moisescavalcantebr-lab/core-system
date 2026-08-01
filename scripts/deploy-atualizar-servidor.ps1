param(
    [string]$Config = "",
    [string]$Server = "",
    [string]$User = "",
    [string]$RemoteZip = "",
    [string]$RemotePath = "",
    [string]$BatchMode = "",
    [switch]$PackageOnly,
    [switch]$SkipProtectedBasesGuard
)

$ErrorActionPreference = "Stop"

$Workspace = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$DefaultConfig = Join-Path $ScriptDir "deploy.local.ps1"

function Get-DeployConfigValue([hashtable]$DeployConfig, [string]$Key, [string]$Default = "") {
    if ($DeployConfig.ContainsKey($Key) -and $null -ne $DeployConfig[$Key]) {
        return [string]$DeployConfig[$Key]
    }

    return $Default
}

function Invoke-DeployNative([string]$Description, [scriptblock]$Command, [int]$Attempts = 3, [int]$DelaySeconds = 8) {
    for ($Attempt = 1; $Attempt -le $Attempts; $Attempt++) {
        if ($Attempts -gt 1) {
            Write-Host "[$Description] tentativa $Attempt/$Attempts" -ForegroundColor DarkCyan
        }

        $PreviousErrorActionPreference = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        & $Command
        $ErrorActionPreference = $PreviousErrorActionPreference
        if ($LASTEXITCODE -eq 0) {
            return $true
        }

        if ($Attempt -lt $Attempts) {
            Write-Host "[$Description] falhou; aguardando $DelaySeconds segundos para tentar novamente..." -ForegroundColor Yellow
            Start-Sleep -Seconds $DelaySeconds
        }
    }

    return $false
}

function New-DeployZip([string]$SourceDir, [string]$DestinationZip) {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem

    if (Test-Path $DestinationZip) {
        Remove-Item -LiteralPath $DestinationZip -Force
    }

    $SourceFull = [System.IO.Path]::GetFullPath($SourceDir).TrimEnd("\", "/")
    $Archive = [System.IO.Compression.ZipFile]::Open($DestinationZip, [System.IO.Compression.ZipArchiveMode]::Create)

    try {
        Get-ChildItem -LiteralPath $SourceFull -Recurse -File | ForEach-Object {
            $Relative = $_.FullName.Substring($SourceFull.Length).TrimStart("\", "/")
            $EntryName = $Relative -replace "\\", "/"
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $Archive,
                $_.FullName,
                $EntryName,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    } finally {
        $Archive.Dispose()
    }
}

if ($Config -eq "" -and (Test-Path $DefaultConfig)) {
    $Config = $DefaultConfig
}

if ($Config -ne "") {
    if (!(Test-Path $Config)) {
        throw "Arquivo de configuracao do deploy nao encontrado: $Config"
    }

    $DeployConfig = . $Config

    if ($DeployConfig -isnot [hashtable]) {
        throw "O arquivo de configuracao deve retornar um hashtable. Veja scripts\deploy.local.example.ps1"
    }

    if ($Server -eq "") {
        $Server = Get-DeployConfigValue $DeployConfig "Server"
    }
    if ($User -eq "") {
        $User = Get-DeployConfigValue $DeployConfig "User"
    }
    if ($RemoteZip -eq "") {
        $RemoteZip = Get-DeployConfigValue $DeployConfig "RemoteZip"
    }
    if ($RemotePath -eq "") {
        $RemotePath = Get-DeployConfigValue $DeployConfig "RemotePath"
    }
    if ($BatchMode -eq "") {
        $BatchMode = Get-DeployConfigValue $DeployConfig "BatchMode" "no"
    }
}

if ($Server -eq "" -and !$PackageOnly) {
    throw "Servidor nao configurado. Informe -Server ou crie scripts\deploy.local.ps1 a partir de scripts\deploy.local.example.ps1"
}

if ($User -eq "") {
    $User = "root"
}

if ($RemoteZip -eq "") {
    $RemoteZip = "/opt/workspace-update.zip"
}

if ($RemotePath -eq "") {
    $RemotePath = "/opt/workspace"
}

if ($BatchMode -eq "") {
    $BatchMode = "no"
}

$BatchMode = $BatchMode.ToLowerInvariant()
if ($BatchMode -notin @("yes", "no")) {
    throw "BatchMode invalido. Use 'yes' para exigir chave SSH ou 'no' para permitir senha."
}

if (!$PackageOnly -and !$SkipProtectedBasesGuard) {
    Write-Host "[guard] Validando bases publicadas antes do deploy..." -ForegroundColor Cyan
    $ProtectedBasesGuard = Join-Path $ScriptDir "servidor-validar-bases-protegidas.ps1"
    if (Test-Path $ProtectedBasesGuard) {
        $GuardArgs = @(
            "-ExecutionPolicy",
            "Bypass",
            "-File",
            $ProtectedBasesGuard,
            "-Server",
            $Server,
            "-User",
            $User,
            "-RemotePath",
            $RemotePath
        )
        if ($BatchMode -ne "") {
            $GuardArgs += @("-BatchMode", $BatchMode)
        }
        if ($Config -ne "") {
            $GuardArgs += @("-Config", $Config)
        }

        & powershell @GuardArgs
        if ($LASTEXITCODE -ne 0) {
            if ($LASTEXITCODE -eq 2) {
                throw "Validacao de bases publicadas falhou por conexao/autenticacao SSH. Confira o acesso SSH para ${User}@${Server} e rode o deploy novamente."
            }

            throw "Validacao de bases publicadas falhou. Sincronize as bases publicadas do servidor com o VS Code/GitHub antes do deploy. Use -SkipProtectedBasesGuard apenas para primeira instalacao ou emergencia."
        }
    }
} elseif (!$PackageOnly -and $SkipProtectedBasesGuard) {
    Write-Host "[guard] Validacao de bases publicadas ignorada por parametro." -ForegroundColor Yellow
}

$DeployDir = Join-Path $Workspace "_deploy"
$Stage = Join-Path $DeployDir "workspace-update"
$Zip = Join-Path $DeployDir "workspace-update.zip"

$StageFull = [System.IO.Path]::GetFullPath($Stage)
$DeployFull = [System.IO.Path]::GetFullPath($DeployDir)
if (!$StageFull.StartsWith($DeployFull)) {
    throw "Caminho de staging invalido: $StageFull"
}

New-Item -ItemType Directory -Force -Path $DeployDir | Out-Null
if (Test-Path $Stage) {
    Remove-Item -LiteralPath $Stage -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $Stage | Out-Null

Write-Host "[1/4] Preparando pacote seguro..." -ForegroundColor Cyan

$IncludeDirs = @(
    "app",
    "bases",
    "cron",
    "docs",
    "docker",
    "modules",
    "scripts",
    "storage\paginas",
    "web"
)

$IncludeFiles = @(
    ".htaccess",
    "index.php",
    "README.md"
)

foreach ($Dir in $IncludeDirs) {
    $Source = Join-Path $Workspace $Dir
    if (Test-Path $Source) {
        $Target = Join-Path $Stage $Dir
        New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Target) | Out-Null
        Copy-Item -LiteralPath $Source -Destination $Target -Recurse -Force
    }
}

foreach ($File in $IncludeFiles) {
    $Source = Join-Path $Workspace $File
    if (Test-Path $Source) {
        Copy-Item -LiteralPath $Source -Destination (Join-Path $Stage $File) -Force
    }
}

Write-Host "[package] Filtrando bases publicadas..." -ForegroundColor Cyan
$StageBasesPath = Join-Path $Stage "bases"
if (Test-Path $StageBasesPath) {
    Get-ChildItem -LiteralPath $StageBasesPath -Directory | ForEach-Object {
        $BaseDir = $_
        $ManifestPath = Join-Path $BaseDir.FullName "base.json"
        $KeepBase = $false

        if ($BaseDir.Name -eq "base") {
            $KeepBase = $false
        } elseif (Test-Path $ManifestPath) {
            try {
                $Manifest = Get-Content -LiteralPath $ManifestPath -Raw | ConvertFrom-Json
                $StageValue = ""
                if ($null -ne $Manifest.PSObject.Properties["base_stage"] -and $null -ne $Manifest.base_stage) {
                    $StageValue = ([string]$Manifest.base_stage).Trim().ToLowerInvariant()
                }

                $ProtectedValue = 0
                if ($null -ne $Manifest.PSObject.Properties["is_protected"] -and $null -ne $Manifest.is_protected) {
                    $ProtectedValue = [int]$Manifest.is_protected
                }

                if ($StageValue -eq "") {
                    if ($ProtectedValue -eq 1) {
                        $StageValue = "published"
                    } else {
                        $StageValue = "laboratory"
                    }
                }

                $KeepBase = $StageValue -eq "published"
            } catch {
                $KeepBase = $false
            }
        }

        if (!$KeepBase) {
            Write-Host "[package] Base fora do deploy: $($BaseDir.Name)" -ForegroundColor DarkYellow
            Remove-Item -LiteralPath $BaseDir.FullName -Recurse -Force
        }
    }
}

if (Test-Path $StageBasesPath) {
    $UntrackedPublishedBases = @()

    Get-ChildItem -LiteralPath $StageBasesPath -Directory | ForEach-Object {
        $ManifestRelativePath = "bases/$($_.Name)/base.json"
        $PreviousErrorActionPreference = $ErrorActionPreference
        $ErrorActionPreference = "Continue"
        & git -C $Workspace ls-files --error-unmatch -- $ManifestRelativePath *> $null
        $GitExitCode = $LASTEXITCODE
        $ErrorActionPreference = $PreviousErrorActionPreference

        if ($GitExitCode -ne 0) {
            $UntrackedPublishedBases += $_.Name
        }
    }

    if ($UntrackedPublishedBases.Count -gt 0) {
        throw "Base(s) publicada(s) ainda nao rastreada(s) no Git: $($UntrackedPublishedBases -join ', '). Faca git add/commit/push antes do deploy."
    }
}

$BlockedPatterns = @(
    "\_notes",
    "\storage\uploads",
    "\storage\logs",
    "\storage\cache",
    "\env",
    "\.git",
    "\.codex",
    "\.agents",
    "\.vscode"
)

foreach ($Pattern in $BlockedPatterns) {
    Get-ChildItem -LiteralPath $Stage -Recurse -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -like "*$Pattern*" } |
        Sort-Object FullName -Descending |
        ForEach-Object { Remove-Item -LiteralPath $_.FullName -Recurse -Force -ErrorAction SilentlyContinue }
}

$BlockedRootDirs = @(
    ".agents",
    ".codex",
    ".git",
    ".vscode",
    "_backups",
    "_deploy",
    "_notes",
    "output",
    "projects",
    "env",
    "tmp"
)

foreach ($Dir in $BlockedRootDirs) {
    $BlockedPath = Join-Path $Stage $Dir
    if (Test-Path $BlockedPath) {
        Remove-Item -LiteralPath $BlockedPath -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$BlockedRelativePaths = @(
    "bases\futebol-amador",
    "scripts\deploy.local.ps1",
    "backup.sql",
    "Docker MySQL.session.sql"
)

foreach ($RelativePath in $BlockedRelativePaths) {
    $BlockedPath = Join-Path $Stage $RelativePath
    if (Test-Path $BlockedPath) {
        Remove-Item -LiteralPath $BlockedPath -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$RequiredDeployFiles = @(
    "app\views\layout_admin.php",
    "app\views\partials\sidebar.php",
    "app\console\sync_pages.php",
    "app\services\projects\ProjectInstaller.php",
    "app\actions\projects\sync.php",
    "app\actions\projects\sync_preview.php",
    "app\actions\projects\modules_sync.php",
    "app\actions\bases\sync_projects.php",
    "web\admin\dashboard.php",
    "web\admin\modules\index.php",
    "web\admin\modules\view.php",
    "modules\financeiro\web\admin\financeiro\categories.php",
    "modules\financeiro\web\admin\financeiro\category_edit.php",
    "modules\financeiro\web\admin\financeiro\index.php",
    "modules\financeiro\web\admin\financeiro\meu_saldo.php",
    "modules\financeiro\web\admin\financeiro\wallet_requests.php",
    "modules\financeiro\web\admin\financeiro\wallet_request_store.php",
    "modules\financeiro\web\admin\financeiro\wallet_request_review.php",
    "storage\paginas\pages",
    "storage\paginas\pages_manifest.json",
    "storage\paginas\blocks",
    "storage\paginas\models"
)

foreach ($File in $RequiredDeployFiles) {
    $RequiredPath = Join-Path $Stage $File
    if (!(Test-Path $RequiredPath)) {
        throw "Arquivo obrigatorio ausente no pacote: $File"
    }
}

$StageRoot = [System.IO.Path]::GetFullPath($Stage).TrimEnd('\', '/')
$PackagedEntries = Get-ChildItem -Path $Stage -Recurse -Force | ForEach-Object {
    $_.FullName.Substring($StageRoot.Length).TrimStart([char[]]@('\', '/')).Replace('\', '/')
}

$ForbiddenPackagePrefixes = @(
    "projects/",
    "env/",
    "_deploy/",
    "_backups/",
    "_notes/",
    "output/",
    "tmp/",
    ".git/",
    ".codex/",
    ".agents/",
    ".vscode/",
    "bases/base/",
    "bases/futebol-amador/",
    "storage/uploads/",
    "storage/logs/",
    "storage/cache/"
)

$ForbiddenPackageFiles = @(
    "scripts/deploy.local.ps1",
    "backup.sql",
    "Docker MySQL.session.sql"
)

$ForbiddenEntries = $PackagedEntries | Where-Object {
    $Entry = $_
    ($ForbiddenPackageFiles -contains $Entry) -or
    ($ForbiddenPackagePrefixes | Where-Object {
        $Entry.StartsWith($_, [System.StringComparison]::OrdinalIgnoreCase)
    } | Select-Object -First 1)
} | Select-Object -First 20

if ($ForbiddenEntries) {
    throw ("Pacote contem arquivos/pastas fora do padrao de producao:`n" + ($ForbiddenEntries -join "`n"))
}

New-DeployZip -SourceDir $Stage -DestinationZip $Zip

$Archive = [System.IO.Compression.ZipFile]::OpenRead($Zip)
try {
    $RequiredZipEntry = "app/actions/projects/sync_preview.php"
    $HasRequiredEntry = $Archive.Entries | Where-Object { $_.FullName -eq $RequiredZipEntry } | Select-Object -First 1
    if (!$HasRequiredEntry) {
        throw "Pacote invalido: entrada ausente no zip: $RequiredZipEntry"
    }
} finally {
    $Archive.Dispose()
}

if ($PackageOnly) {
    Write-Host "[package] Pacote de deploy preparado: $Zip" -ForegroundColor Green
    Write-Host "[package] Nenhum arquivo foi enviado ao servidor." -ForegroundColor Yellow
    return
}

Write-Host "[2/4] Enviando pacote para o servidor..." -ForegroundColor Cyan
$ScpArgs = @("-o", "BatchMode=$BatchMode", "-o", "ConnectTimeout=20", $Zip, "${User}@${Server}:$RemoteZip")
$Sent = Invoke-DeployNative "scp" { & scp.exe @ScpArgs } 3 10
if (!$Sent) {
    throw "Falha ao enviar pacote para o servidor."
}

Write-Host "[3/4] Aplicando arquivos no servidor..." -ForegroundColor Cyan
$RemoteRelease = "/tmp/workspace-release-" + (Get-Date -Format "yyyyMMddHHmmss")
$RemoteCommand = @"
set -e
mkdir -p $RemotePath
rm -rf "$RemoteRelease"
mkdir -p "$RemoteRelease"
echo "[release] extraindo pacote em $RemoteRelease"
if command -v unzip >/dev/null 2>&1; then
  unzip -oq "$RemoteZip" -d "$RemoteRelease"
else
  python3 -m zipfile -e "$RemoteZip" "$RemoteRelease"
fi
if ! test -f "$RemoteRelease/app/actions/projects/sync_preview.php"; then
  echo "ERRO: pacote extraido sem app/actions/projects/sync_preview.php"
  echo "[debug] primeiras entradas do zip:"
  python3 -c 'import zipfile; z=zipfile.ZipFile(r"""$RemoteZip"""); print("\n".join(i.filename for i in z.infolist()[:20])); z.close()'
  echo "[debug] estrutura em ${RemoteRelease}:"
  find "$RemoteRelease" -maxdepth 3 -type f | head -40
  exit 1
fi
if [ -d "$RemoteRelease/docker" ]; then
  mkdir -p "$RemotePath/docker"
  cp -a "$RemoteRelease/docker/." "$RemotePath/docker"
fi
echo "[docker] garantindo containers do servidor"
cd "$RemotePath/docker"
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
echo "[docker] aguardando banco"
for i in `$(seq 1 60); do
  if docker exec app_db mysqladmin ping -uroot -proot --silent >/dev/null 2>&1; then
    break
  fi
  sleep 2
done
docker exec app_db mysqladmin ping -uroot -proot --silent >/dev/null
echo "[docker] aguardando app php"
for i in `$(seq 1 40); do
  if docker exec app_php php -v >/dev/null 2>&1; then
    break
  fi
  sleep 1
done
docker exec app_php php -v >/dev/null
echo "[release] preparando pacote temporario no container"
docker exec app_php sh -lc 'rm -rf /tmp/workspace-release && mkdir -p /tmp/workspace-release'
docker cp "$RemoteRelease/." "app_php:/tmp/workspace-release"
if ! docker exec app_php test -f /tmp/workspace-release/app/actions/projects/sync_preview.php; then
  echo "ERRO: pacote temporario no container nao contem sync_preview.php"
  docker exec app_php find /tmp/workspace-release -maxdepth 3 -type f | head -40
  exit 1
fi
echo "[host] aplicando release validada"
rm -rf "$RemotePath/app" "$RemotePath/cron" "$RemotePath/docs" "$RemotePath/modules" "$RemotePath/scripts" "$RemotePath/web" "$RemotePath/storage/paginas"
if [ -d "$RemoteRelease/app" ]; then
  cp -a "$RemoteRelease/app" "$RemotePath/app"
fi
if [ -d "$RemoteRelease/bases" ]; then
  mkdir -p "$RemotePath/bases"
  rm -rf "$RemotePath/bases/base"
  find "$RemoteRelease/bases" -mindepth 1 -maxdepth 1 -type d | while read base_dir; do
    base_name=`$(basename "$base_dir")
    rm -rf "$RemotePath/bases/`$base_name"
    cp -a "$base_dir" "$RemotePath/bases/`$base_name"
  done
fi
if [ -d "$RemoteRelease/cron" ]; then
  cp -a "$RemoteRelease/cron" "$RemotePath/cron"
fi
if [ -d "$RemoteRelease/docs" ]; then
  cp -a "$RemoteRelease/docs" "$RemotePath/docs"
fi
if [ -d "$RemoteRelease/docker" ]; then
  mkdir -p "$RemotePath/docker"
  cp -a "$RemoteRelease/docker/." "$RemotePath/docker"
fi
if [ -d "$RemoteRelease/modules" ]; then
  cp -a "$RemoteRelease/modules" "$RemotePath/modules"
fi
if [ -d "$RemoteRelease/scripts" ]; then
  cp -a "$RemoteRelease/scripts" "$RemotePath/scripts"
fi
if [ -d "$RemoteRelease/web" ]; then
  cp -a "$RemoteRelease/web" "$RemotePath/web"
fi
if [ -d "$RemoteRelease/storage/paginas" ]; then
  mkdir -p "$RemotePath/storage"
  cp -a "$RemoteRelease/storage/paginas" "$RemotePath/storage/paginas"
fi
if [ -f "$RemoteRelease/.htaccess" ]; then
  cp -a "$RemoteRelease/.htaccess" "$RemotePath/.htaccess"
fi
if [ -f "$RemoteRelease/index.php" ]; then
  cp -a "$RemoteRelease/index.php" "$RemotePath/index.php"
fi
if [ -f "$RemoteRelease/README.md" ]; then
  cp -a "$RemoteRelease/README.md" "$RemotePath/README.md"
fi
echo "[container] aplicando release validada"
docker exec app_php rm -rf /var/www/html/app /var/www/html/cron /var/www/html/docs /var/www/html/modules /var/www/html/scripts /var/www/html/web /var/www/html/storage/paginas
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/app ]; then cp -a /tmp/workspace-release/app /var/www/html/app; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/bases ]; then mkdir -p /var/www/html/bases; rm -rf /var/www/html/bases/base; find /tmp/workspace-release/bases -mindepth 1 -maxdepth 1 -type d | while read base_dir; do base_name=$(basename "$base_dir"); rm -rf "/var/www/html/bases/$base_name"; cp -a "$base_dir" "/var/www/html/bases/$base_name"; done; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/cron ]; then cp -a /tmp/workspace-release/cron /var/www/html/cron; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/docs ]; then cp -a /tmp/workspace-release/docs /var/www/html/docs; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/modules ]; then cp -a /tmp/workspace-release/modules /var/www/html/modules; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/scripts ]; then cp -a /tmp/workspace-release/scripts /var/www/html/scripts; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/web ]; then cp -a /tmp/workspace-release/web /var/www/html/web; fi'
docker exec app_php sh -lc 'if [ -d /tmp/workspace-release/storage/paginas ]; then mkdir -p /var/www/html/storage && cp -a /tmp/workspace-release/storage/paginas /var/www/html/storage/paginas; fi'
docker exec app_php sh -lc 'if [ -f /tmp/workspace-release/.htaccess ]; then cp -a /tmp/workspace-release/.htaccess /var/www/html/.htaccess; fi'
docker exec app_php sh -lc 'if [ -f /tmp/workspace-release/index.php ]; then cp -a /tmp/workspace-release/index.php /var/www/html/index.php; fi'
docker exec app_php sh -lc 'if [ -f /tmp/workspace-release/README.md ]; then cp -a /tmp/workspace-release/README.md /var/www/html/README.md; fi'
if ! docker exec app_php test -f /var/www/html/index.php; then
  echo "ERRO: index.php ausente no container apos aplicar release"
  exit 1
fi
echo "[bootstrap] migrando/registrando Core"
docker exec app_php php -r 'require "/var/www/html/app/bootstrap/bootstrap.php"; echo "[bootstrap] OK\n";'
echo "[fix] permissao das pastas mutaveis"
docker exec app_php sh -lc 'for path in app bases cron docs modules scripts web; do if [ -e "/var/www/html/$path" ]; then chown -R www-data:www-data "/var/www/html/$path" && chmod -R u+rwX,g+rwX,o+rX "/var/www/html/$path"; fi; done'
docker exec app_php sh -lc 'mkdir -p /var/www/html/bases /var/www/html/projects /var/www/html/storage && chown -R www-data:www-data /var/www/html/bases /var/www/html/projects /var/www/html/storage && chmod -R u+rwX,g+rwX,o+rX /var/www/html/bases /var/www/html/projects /var/www/html/storage'
echo "[db] deploy de arquivos concluido - schema do Core fica no instalador/reparo dedicado"
echo "[check] host sync_preview.php"
if ! grep -n base_slug $RemotePath/app/actions/projects/sync_preview.php; then
  echo "ERRO: sync_preview.php no host nao contem base_slug"
  ls -l $RemotePath/app/actions/projects/sync_preview.php
  sed -n '1,40p' $RemotePath/app/actions/projects/sync_preview.php
  exit 1
fi
echo "[check] container sync_preview.php"
if ! docker exec app_php grep -n base_slug /var/www/html/app/actions/projects/sync_preview.php; then
  echo "ERRO: sync_preview.php no container nao contem base_slug"
  docker exec app_php ls -l /var/www/html/app/actions/projects/sync_preview.php
  docker exec app_php sed -n '1,40p' /var/www/html/app/actions/projects/sync_preview.php
  exit 1
fi
echo "[check] host modules/index.php"
if ! grep -n "Principais" $RemotePath/web/admin/modules/index.php || ! grep -n "Addons" $RemotePath/web/admin/modules/index.php; then
  echo "ERRO: modules/index.php no host nao contem abas Principais/Addons"
  ls -l $RemotePath/web/admin/modules/index.php
  sed -n '160,190p' $RemotePath/web/admin/modules/index.php
  exit 1
fi
echo "[check] container modules/index.php"
if ! docker exec app_php grep -n "Principais" /var/www/html/web/admin/modules/index.php || ! docker exec app_php grep -n "Addons" /var/www/html/web/admin/modules/index.php; then
  echo "ERRO: modules/index.php no container nao contem abas Principais/Addons"
  docker exec app_php ls -l /var/www/html/web/admin/modules/index.php
  docker exec app_php sed -n '160,190p' /var/www/html/web/admin/modules/index.php
  exit 1
fi
echo "[check] paginas publicas"
if ! test -d $RemotePath/storage/paginas/pages || ! test -d $RemotePath/storage/paginas/blocks || ! test -d $RemotePath/storage/paginas/models; then
  echo "ERRO: estrutura de paginas ausente no host"
  ls -l $RemotePath/storage || true
  exit 1
fi
HostPagesCount=`$(find $RemotePath/storage/paginas/pages -maxdepth 1 -type f -name '*.json' | wc -l)
ContainerPagesCount=`$(docker exec app_php find /var/www/html/storage/paginas/pages -maxdepth 1 -type f -name '*.json' | wc -l)
if [ "`$HostPagesCount" -eq 0 ]; then
  echo "ERRO: nenhuma pagina JSON encontrada no host"
  ls -l $RemotePath/storage/paginas/pages
  exit 1
fi
if [ "`$HostPagesCount" -ne "`$ContainerPagesCount" ]; then
  echo "ERRO: quantidade de paginas no container difere do host. host=`$HostPagesCount container=`$ContainerPagesCount"
  docker exec app_php ls -l /var/www/html/storage/paginas/pages
  exit 1
fi
echo "[check] host financeiro saldo"
if ! grep -n Saldo $RemotePath/modules/financeiro/web/admin/financeiro/meu_saldo.php; then
  echo "ERRO: modulo financeiro no host nao contem a tela nova de saldo"
  ls -l $RemotePath/modules/financeiro/web/admin/financeiro/meu_saldo.php
  exit 1
fi
echo "[check] container financeiro saldo"
if ! docker exec app_php grep -n Saldo /var/www/html/modules/financeiro/web/admin/financeiro/meu_saldo.php; then
  echo "ERRO: modulo financeiro no container nao contem a tela nova de saldo"
  docker exec app_php ls -l /var/www/html/modules/financeiro/web/admin/financeiro/meu_saldo.php
  exit 1
fi
echo "[check] php lint container"
for file in \
  /var/www/html/app/views/layout_admin.php \
  /var/www/html/app/views/partials/sidebar.php \
  /var/www/html/app/services/projects/ProjectInstaller.php \
  /var/www/html/app/actions/projects/modules_sync.php \
  /var/www/html/app/actions/bases/sync_projects.php \
  /var/www/html/web/admin/dashboard.php \
  /var/www/html/web/admin/modules/index.php \
  /var/www/html/web/admin/modules/view.php \
  /var/www/html/modules/financeiro/web/admin/financeiro/categories.php \
  /var/www/html/modules/financeiro/web/admin/financeiro/index.php \
  /var/www/html/modules/financeiro/web/admin/financeiro/meu_saldo.php \
  /var/www/html/modules/financeiro/web/admin/financeiro/wallet_requests.php; do
  docker exec app_php php -l "`$file" || exit 1
done
echo "[done] release aplicada e verificada"
"@

$RemoteCommand = $RemoteCommand -replace "`r`n", "`n" -replace "`r", "`n"
$SshArgs = @("-o", "BatchMode=$BatchMode", "-o", "ConnectTimeout=20", "${User}@${Server}", $RemoteCommand)
$Applied = Invoke-DeployNative "ssh" { & ssh.exe @SshArgs } 3 10
if (!$Applied) {
    throw "Falha ao aplicar/verificar arquivos no servidor."
}

Write-Host "[4/4] Atualizacao concluida." -ForegroundColor Green
Write-Host "Verificacao: core e modulos ativos atualizados no host e no container." -ForegroundColor Green
Write-Host "Importante: projetos existentes nao sao enviados no pacote. No Core, use Sync Tudo na base ou Sincronizar Modulos no projeto para atualizar /projects." -ForegroundColor Yellow
Write-Host "Site: https://meuprojetoweb.com"
