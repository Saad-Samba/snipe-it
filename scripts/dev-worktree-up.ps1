[CmdletBinding()]
param(
    [switch]$Build,
    [switch]$SeedLicenseReuseQa
)

$ErrorActionPreference = 'Stop'

function ConvertTo-Slug([string]$Value) {
    $slug = $Value.ToLowerInvariant() -replace '[^a-z0-9]', '-'
    $slug = $slug -replace '-{2,}', '-'
    return $slug.Trim('-')
}

function Test-PortInUse([int]$Port) {
    $listeners = [System.Net.NetworkInformation.IPGlobalProperties]::GetIPGlobalProperties().GetActiveTcpListeners()
    return ($listeners | Where-Object { $_.Port -eq $Port } | Measure-Object).Count -gt 0
}

function Find-FreePort([int]$StartPort) {
    $port = $StartPort
    while (Test-PortInUse $port) {
        $port++
    }
    return $port
}

function Read-EnvFile([string]$Path) {
    $values = @{}
    foreach ($line in Get-Content $Path) {
        if ($line -match '^([^#=]+)=(.*)$') {
            $values[$matches[1].Trim()] = $matches[2].Trim()
        }
    }
    return $values
}

$rootDir = (& git rev-parse --show-toplevel).Trim()
if ($LASTEXITCODE -ne 0 -or -not $rootDir) {
    throw 'Run this script from inside the Snipe-IT Git repository.'
}

$worktreeName = Split-Path $rootDir -Leaf
$envFile = Join-Path $rootDir '.env.worktree'
$composeFile = Join-Path $rootDir 'dev.docker-compose.yml'

if (-not (Test-Path $envFile)) {
    $projectName = ConvertTo-Slug $worktreeName
    $appPort = Find-FreePort 8000
    $dbPort = Find-FreePort 3306
    $mailhogPort = Find-FreePort 8025
    $cookieName = (ConvertTo-Slug "$projectName-session")
    $cachePrefix = ConvertTo-Slug $projectName

    @(
        "COMPOSE_PROJECT_NAME=$projectName"
        "APP_PORT=$appPort"
        "DB_PORT=$dbPort"
        "MAILHOG_PORT=$mailhogPort"
        "APP_URL=http://localhost:$appPort"
        "COOKIE_NAME=$cookieName"
        "CACHE_PREFIX=$cachePrefix"
    ) | Set-Content -Path $envFile -Encoding ASCII
}

$environment = Read-EnvFile $envFile
$composeArgs = @(
    'compose',
    '--project-name', $environment.COMPOSE_PROJECT_NAME,
    '--env-file', $envFile,
    '-f', $composeFile,
    'up', '-d'
)
if ($Build) {
    $composeArgs += '--build'
}

& docker @composeArgs
if ($LASTEXITCODE -ne 0) {
    throw 'Docker Compose failed to start the worktree stack.'
}

if ($SeedLicenseReuseQa) {
    Write-Host 'Waiting for the application container to finish startup...'
    $ready = $false
    $readyArgs = @(
        'compose',
        '--project-name', $environment.COMPOSE_PROJECT_NAME,
        '--env-file', $envFile,
        '-f', $composeFile,
        'exec', '-T', 'snipeit',
        'sh', '-c', 'ps | grep -q "[h]ttpd"'
    )
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        & docker @readyArgs *> $null
        if ($LASTEXITCODE -eq 0) {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 2
    }
    if (-not $ready) {
        throw 'The application container did not become ready within two minutes.'
    }

    $seedArgs = @(
        'compose',
        '--project-name', $environment.COMPOSE_PROJECT_NAME,
        '--env-file', $envFile,
        '-f', $composeFile,
        'exec', '-T', 'snipeit',
        'php', 'artisan', 'db:seed',
        '--class=Database\Seeders\ManualLicenseReuseQaSeeder',
        '--force'
    )
    & docker @seedArgs
    if ($LASTEXITCODE -ne 0) {
        throw 'The stack started, but the license reuse QA seeder failed.'
    }
}

Write-Host ''
Write-Host 'Started worktree-local Snipe-IT stack.'
Write-Host "Worktree: $worktreeName"
Write-Host "Project: $($environment.COMPOSE_PROJECT_NAME)"
Write-Host "App URL: $($environment.APP_URL)"
Write-Host "DB Port: $($environment.DB_PORT)"
Write-Host "MailHog URL: http://localhost:$($environment.MAILHOG_PORT)"
Write-Host "Env File: $envFile"
