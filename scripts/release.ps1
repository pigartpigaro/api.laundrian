[CmdletBinding()]
param(
    [switch]$Help,
    [switch]$DryRun
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

function Invoke-WorktreeGit {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)
    & git -C $tempRoot @Arguments
    if ($LASTEXITCODE -ne 0) { throw "git $($Arguments -join ' ') gagal." }
}

if ($Help) {
    Write-Output 'composer run release memvalidasi develop lalu fast-forward dan push ke main. Gunakan -DryRun untuk validasi tanpa push.'
    exit 0
}

try {
    if (Get-GitOutput status --porcelain) { throw 'working tree harus bersih.' }
    if ((Get-GitOutput branch --show-current) -ne 'develop') { throw 'jalankan release dari branch develop.' }
    Invoke-Git fetch origin --prune

    $divergence = (Get-GitOutput rev-list --left-right --count develop...origin/develop) -split '\s+' | ForEach-Object { [int]$_ }
    if ($divergence[0] -ne 0 -or $divergence[1] -ne 0) { throw 'develop lokal dan origin/develop harus sinkron; push commit develop terlebih dahulu.' }
    $commitsToRelease = [int](Get-GitOutput rev-list --count "origin/$target..origin/develop")
    if ($commitsToRelease -eq 0) { throw "tidak ada commit baru dari develop untuk $target." }
    & git -C $root merge-base --is-ancestor "origin/$target" origin/develop
    if ($LASTEXITCODE -ne 0) { throw 'origin/main bukan ancestor origin/develop. Sinkronkan branch secara manual; release hanya melakukan fast-forward.' }

    Invoke-Git worktree add --detach $tempRoot "origin/$target"
    Invoke-WorktreeGit merge --ff-only origin/develop
    Push-Location $tempRoot
    try {
        & composer install --no-interaction --prefer-dist --no-progress
        if ($LASTEXITCODE -ne 0) { throw 'composer install gagal.' }
        Copy-Item '.env.example' '.env'
        & php artisan key:generate --force
        if ($LASTEXITCODE -ne 0) { throw 'gagal membuat APP_KEY sementara untuk test.' }
        $env:APP_ENV = 'testing'
        $env:DB_CONNECTION = 'sqlite'
        $env:DB_DATABASE = ':memory:'
        & php artisan test
        if ($LASTEXITCODE -ne 0) { throw 'php artisan test gagal.' }
    } finally {
        Pop-Location
    }

    if ($DryRun) {
        Write-Output "`nDry run selesai. Test lolos; main tidak di-push. $commitsToRelease commit siap dirilis."
    } else {
        $previousPush = $env:LAUNDRIAN_RELEASE_PUSH
        $previousRef = $env:LAUNDRIAN_RELEASE_REF
        try {
            $env:LAUNDRIAN_RELEASE_PUSH = '1'
            $env:LAUNDRIAN_RELEASE_REF = 'refs/heads/main'
            $releaseSha = (& git -C $tempRoot rev-parse HEAD | Out-String).Trim()
            if ($LASTEXITCODE -ne 0) { throw 'gagal membaca commit hasil validasi.' }
            Invoke-Git push origin "${releaseSha}:refs/heads/main"
        } finally {
            $env:LAUNDRIAN_RELEASE_PUSH = $previousPush
            $env:LAUNDRIAN_RELEASE_REF = $previousRef
        }
        Write-Output "`nRelease selesai: $commitsToRelease commit dari develop sudah di-fast-forward ke origin/main."
        Write-Output 'Branch kerja tetap develop. Deployment mengikuti konfigurasi repository production.'
    }
} catch {
    Write-Error "Release dibatalkan: $($_.Exception.Message)"
    exit 1
} finally {
    if (Test-Path $tempRoot) {
        & git -C $root worktree remove --force $tempRoot 2>$null
        Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}
