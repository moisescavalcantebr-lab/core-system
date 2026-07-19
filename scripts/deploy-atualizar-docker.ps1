param(
    [string]$Container = "app_php",
    [string]$ContainerPath = "/var/www/html",
    [switch]$SkipLint
)

$ErrorActionPreference = "Stop"

$Workspace = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
$DeployDir = Join-Path $Workspace "_deploy"
$Stage = Join-Path $DeployDir "docker-update"

$StageFull = [System.IO.Path]::GetFullPath($Stage)
$DeployFull = [System.IO.Path]::GetFullPath($DeployDir)
if (!$StageFull.StartsWith($DeployFull)) {
    throw "Caminho de staging invalido: $StageFull"
}

function Copy-DeployPath([string]$RelativePath) {
    $Source = Join-Path $Workspace $RelativePath
    if (!(Test-Path $Source)) {
        return
    }

    $Target = Join-Path $Stage $RelativePath
    New-Item -ItemType Directory -Force -Path (Split-Path -Parent $Target) | Out-Null
    Copy-Item -LiteralPath $Source -Destination $Target -Recurse -Force
}

function Remove-IfExists([string]$Path) {
    if (Test-Path $Path) {
        Remove-Item -LiteralPath $Path -Recurse -Force -ErrorAction SilentlyContinue
    }
}

Write-Host "[1/4] Preparando pacote local seguro..." -ForegroundColor Cyan

New-Item -ItemType Directory -Force -Path $DeployDir | Out-Null
Remove-IfExists $Stage
New-Item -ItemType Directory -Force -Path $Stage | Out-Null

$IncludeDirs = @(
    "app",
    "bases",
    "cron",
    "modules",
    "projects",
    "storage\paginas",
    "web"
)

$IncludeFiles = @(
    ".htaccess",
    "index.php",
    "README.md"
)

foreach ($Dir in $IncludeDirs) {
    Copy-DeployPath $Dir
}

foreach ($File in $IncludeFiles) {
    Copy-DeployPath $File
}

$BlockedPatterns = @(
    "\_notes",
    "\storage\uploads",
    "\storage\logs",
    "\storage\cache",
    "\env",
    "\.git"
)

foreach ($Pattern in $BlockedPatterns) {
    Get-ChildItem -LiteralPath $Stage -Recurse -Force -ErrorAction SilentlyContinue |
        Where-Object { $_.FullName -like "*$Pattern*" } |
        Sort-Object FullName -Descending |
        ForEach-Object { Remove-IfExists $_.FullName }
}

$BlockedRootDirs = @(
    "env",
    "_deploy"
)

foreach ($Dir in $BlockedRootDirs) {
    Remove-IfExists (Join-Path $Stage $Dir)
}

$RequiredDeployFiles = @(
    "app\bootstrap\bootstrap.php",
    "app\console\sync_pages.php",
    "app\actions\projects\sync.php",
    "app\actions\projects\modules_sync.php",
    "app\actions\bases\sync_projects.php",
    "bases\base\web\admin\dashboard.php",
    "bases\base\web\admin\upgrade\index.php",
    "bases\base\web\admin\saldo.php",
    "modules\financeiro\module.json",
    "web\admin\modules\index.php",
    "web\admin\bases\index.php",
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

Write-Host "[2/4] Copiando arquivos para o container $Container..." -ForegroundColor Cyan

docker exec $Container sh -lc "mkdir -p '$ContainerPath'"
if ($LASTEXITCODE -ne 0) {
    throw "Container nao encontrado ou indisponivel: $Container"
}

$WorkspaceFull = [System.IO.Path]::GetFullPath($Workspace).TrimEnd("\", "/")
$ContainerPathNormalized = $ContainerPath.TrimEnd("/")
$UsesWorkspaceBindMount = $false
$ContainerPathBindSource = ""
$ContainerInspectJson = docker inspect $Container
if ($LASTEXITCODE -eq 0) {
    $ContainerInfo = $ContainerInspectJson | ConvertFrom-Json
    foreach ($Mount in $ContainerInfo[0].Mounts) {
        $MountSource = ([string]$Mount.Source).Trim()
        $MountDestination = ([string]$Mount.Destination).Trim().TrimEnd("/")
        try {
            $MountSourceFull = [System.IO.Path]::GetFullPath($MountSource).TrimEnd("\", "/")
        } catch {
            $MountSourceFull = $MountSource.TrimEnd("\", "/")
        }

        if ($MountDestination -eq $ContainerPathNormalized) {
            $ContainerPathBindSource = $MountSourceFull
            if ([string]::Equals($MountSourceFull, $WorkspaceFull, [System.StringComparison]::OrdinalIgnoreCase)) {
                $UsesWorkspaceBindMount = $true
            }
            break
        }
    }
}

if ($ContainerPathBindSource -ne "" -and !$UsesWorkspaceBindMount) {
    throw "O container $Container monta $ContainerPath a partir de outro workspace: $ContainerPathBindSource. Recrie/use o container deste workspace antes de atualizar o Docker local."
}

if ($UsesWorkspaceBindMount) {
    Write-Host "[2/4] Container usa bind mount do workspace; pulando limpeza e copia para preservar arquivos locais." -ForegroundColor Yellow
} else {
    $ManagedDirs = @(".vscode", "_deploy", "_notes", "app", "bases", "cron", "modules", "output", "projects", "scripts", "tmp", "web")
    foreach ($Dir in $ManagedDirs) {
        docker exec $Container sh -lc "rm -rf '$ContainerPath/$Dir'"
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao limpar pasta gerenciada no container: $Dir"
        }
    }

    docker exec $Container sh -lc "rm -rf '$ContainerPath/storage/paginas'"
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao limpar paginas no container."
    }

    foreach ($Dir in $IncludeDirs) {
        $Source = Join-Path $Stage $Dir
        if (!(Test-Path $Source)) {
            continue
        }

        $ContainerDir = ($Dir -replace "\\", "/")
        docker exec $Container sh -lc "mkdir -p '$ContainerPath/$ContainerDir'"
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao criar pasta no container: $ContainerPath/$ContainerDir"
        }

        docker cp "$Source\." "${Container}:$ContainerPath/$ContainerDir"
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao copiar pasta para o container: $Dir"
        }
    }

    foreach ($File in $IncludeFiles) {
        $Source = Join-Path $Stage $File
        if (!(Test-Path $Source)) {
            continue
        }

        docker cp $Source "${Container}:$ContainerPath/$File"
        if ($LASTEXITCODE -ne 0) {
            throw "Falha ao copiar arquivo para o container: $File"
        }
    }

    Write-Host "[3/4] Ajustando permissoes locais do container..." -ForegroundColor Cyan
    docker exec $Container sh -lc "for path in app bases cron modules web; do if [ -e '$ContainerPath/'`$path ]; then chown -R www-data:www-data '$ContainerPath/'`$path && chmod -R u+rwX,g+rwX,o+rX '$ContainerPath/'`$path; fi; done"
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao ajustar permissoes das pastas gerenciadas no container."
    }

    docker exec $Container sh -lc "mkdir -p '$ContainerPath/bases' '$ContainerPath/projects' '$ContainerPath/storage' && chown -R www-data:www-data '$ContainerPath/bases' '$ContainerPath/projects' '$ContainerPath/storage' && chmod -R u+rwX,g+rwX,o+rX '$ContainerPath/bases' '$ContainerPath/projects' '$ContainerPath/storage'"
    if ($LASTEXITCODE -ne 0) {
        throw "Falha ao ajustar permissoes no container."
    }
}

Write-Host "[sync] Registrando paginas publicas no Core..." -ForegroundColor Cyan
docker exec $Container php "$ContainerPath/app/console/sync_pages.php"
if ($LASTEXITCODE -ne 0) {
    throw "Falha ao sincronizar paginas publicas no Core."
}

if (!$SkipLint) {
    Write-Host "[check] Validando PHP principal..." -ForegroundColor Cyan

    $LintFiles = @(
        "app/views/layout_admin.php",
        "app/views/partials/sidebar.php",
        "app/services/projects/ProjectInstaller.php",
        "app/actions/projects/modules_sync.php",
        "app/actions/bases/sync_projects.php",
        "web/admin/dashboard.php",
        "modules/financeiro/web/admin/financeiro/categories.php",
        "modules/financeiro/web/admin/financeiro/category_edit.php",
        "modules/financeiro/web/admin/financeiro/index.php",
        "modules/financeiro/web/admin/financeiro/meu_saldo.php",
        "modules/financeiro/web/admin/financeiro/wallet_requests.php",
        "modules/financeiro/web/admin/financeiro/wallet_request_store.php",
        "modules/financeiro/web/admin/financeiro/wallet_request_review.php",
        "web/admin/modules/index.php",
        "web/admin/modules/view.php",
        "bases/base/web/admin/dashboard.php",
        "bases/base/web/admin/upgrade/index.php",
        "bases/base/web/admin/upgrade/checkout.php",
        "bases/base/web/admin/upgrade/request.php",
        "bases/base/web/admin/saldo.php",
        "bases/base/web/admin/saldo_historico.php",
        "bases/base/app/helpers/core_bridge.php",
        "bases/base/app/views/partials/header_admin.php",
        "bases/base/app/views/partials/footer.php"
    )

    foreach ($File in $LintFiles) {
        docker exec $Container php -l "$ContainerPath/$File"
        if ($LASTEXITCODE -ne 0) {
            throw "Erro de sintaxe PHP no container: $File"
        }
    }
}

Write-Host "[4/4] Docker local atualizado." -ForegroundColor Green
Write-Host "Origem: $Workspace" -ForegroundColor Green
Write-Host "Destino: ${Container}:$ContainerPath" -ForegroundColor Green
Write-Host "Agora teste no localhost. Se estiver tudo certo, rode o deploy do servidor." -ForegroundColor Yellow
