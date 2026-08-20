$ErrorActionPreference = 'Stop'

$repoRoot = (git rev-parse --show-toplevel 2>$null)
if (-not $repoRoot) {
    throw 'A history scan csak .git mappát tartalmazó repositoryban futtatható.'
}

Set-Location $repoRoot

if (-not (Get-Command gitleaks -ErrorAction SilentlyContinue)) {
    Write-Host 'A Gitleaks nincs telepítve.' -ForegroundColor Yellow
    Write-Host 'Telepítés után futtasd újra ezt a scriptet: https://github.com/gitleaks/gitleaks'
    exit 2
}

$reportPath = Join-Path $repoRoot 'gitleaks-report.json'
& gitleaks git --redact --verbose --report-format json --report-path $reportPath .
$exitCode = $LASTEXITCODE

if ($exitCode -ne 0) {
    Write-Host "A scan találatot vagy futási hibát jelzett. Jelentés: $reportPath" -ForegroundColor Red
    Write-Host 'A talált titkot előbb vond vissza/rotáld; pusztán a commit törlése nem elég.'
    exit $exitCode
}

Write-Host 'Git history secret scan: nincs találat.' -ForegroundColor Green
