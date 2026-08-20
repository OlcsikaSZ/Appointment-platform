$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot "..")

Push-Location $RepoRoot

try {
    Write-Host ""
    Write-Host "========================================"
    Write-Host " Olcsi Business production deploy"
    Write-Host "========================================"
    Write-Host ""

    Write-Host "[1/5] Branch ellenorzese..."

    $branch = git branch --show-current

    if ($branch -ne "main") {
        throw "A production deploy csak a main branch-rol engedelyezett. Jelenlegi branch: $branch"
    }

    Write-Host "main branch: OK"

    Write-Host ""
    Write-Host "[2/5] Working tree ellenorzese..."

    $changes = git status --porcelain

    if ($changes) {
        Write-Host ""
        git status --short
        throw "Van nem commitolt valtozas. Deployment megszakitva."
    }

    Write-Host "Working tree clean: OK"

    Write-Host ""
    Write-Host "[3/5] Repository hygiene ellenorzese..."

    powershell `
        -NoProfile `
        -ExecutionPolicy Bypass `
        -File (Join-Path $PSScriptRoot "verify-repository-hygiene.ps1")

    if ($LASTEXITCODE -ne 0) {
        throw "Repository hygiene ellenorzes sikertelen."
    }

    Write-Host ""
    Write-Host "[4/5] GitHub szinkron ellenorzese..."

    git fetch origin main

    if ($LASTEXITCODE -ne 0) {
        throw "git fetch sikertelen."
    }

    $localCommit = git rev-parse HEAD
    $remoteCommit = git rev-parse origin/main

    if ($localCommit -ne $remoteCommit) {
        Write-Host ""
        Write-Host "Local : $localCommit"
        Write-Host "GitHub: $remoteCommit"
        throw "A helyi main es az origin/main nem ugyanaz. Push vagy pull szukseges."
    }

    Write-Host "Local main = GitHub main: OK"

    Write-Host ""
    Write-Host "[5/5] Rackhost production deploy inditasa..."
    Write-Host ""

    ssh -t olcsi-rackhost "~/deploy-production.sh"

    if ($LASTEXITCODE -ne 0) {
        throw "A Rackhost deployment hibaval allt le."
    }

    Write-Host ""
    Write-Host "========================================"
    Write-Host " Production deploy befejezve."
    Write-Host "========================================"
}
finally {
    Pop-Location
}