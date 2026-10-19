[CmdletBinding()]
param(
    [switch]$Help
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$target = 'main'
$tempRoot = Join-Path ([System.IO.Path]::GetTempPath()) ("laundrian-api-release-" + [Guid]::NewGuid().ToString('N'))

function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & git -C $root @Arguments
    if ($LASTEXITCODE -ne 0) { throw "git $($Arguments -join ' ') gagal." }
}

function Get-GitOutput {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    $output = & git -C $root @Arguments
    if ($LASTEXITCODE -ne 0) { throw "git $($Arguments -join ' ') gagal." }
    return ($output | Out-String).Trim()
}

if ($Help) {
    Write-Output 'Release backend memvalidasi branch develop dalam worktree sementara. Script tidak merge atau push ke production.'
    exit 0
}

try {
    if (Get-GitOutput status --porcelain) { throw 'working tree harus bersih.' }
    Invoke-Git fetch origin --prune

    $divergence = (Get-GitOutput rev-list --left-right --count develop...origin/develop) -split '\s+' | ForEach-Object { [int]$_ }
    if ($divergence[0] -ne 0 -or $divergence[1] -ne 0) { throw 'develop lokal dan origin/develop harus sinkron; push commit develop terlebih dahulu.' }
    if ((Get-GitOutput rev-list --count "origin/$target..origin/develop") -eq '0') { throw "tidak ada commit baru dari develop untuk $target." }

    Invoke-Git worktree add --detach $tempRoot origin/develop
    Push-Location $tempRoot
    try {
        & composer install --no-interaction --prefer-dist --no-progress
        if ($LASTEXITCODE -ne 0) { throw 'composer install gagal.' }
        Copy-Item '.env.example' '.env'
        $env:APP_ENV = 'testing'
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = ':memory:'
        & php artisan test
        if ($LASTEXITCODE -ne 0) { throw 'php artisan test gagal.' }
    } finally {
        Pop-Location
    }

    Write-Output "Validasi release backend selesai. Tidak ada merge atau push ke $target. Buat Pull Request develop -> $target di GitHub."
} finally {
    if (Test-Path $tempRoot) {
        & git -C $root worktree remove --force $tempRoot 2>$null
        Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
