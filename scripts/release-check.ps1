$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$RepoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Push-Location $RepoRoot

try {
    Write-Host ''
    Write-Host '========================================'
    Write-Host ' Appointment Platform release check'
    Write-Host '========================================'

    Write-Host "`n[1/4] Repository hygiene..."
    powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'verify-repository-hygiene.ps1')
    if ($LASTEXITCODE -ne 0) {
        throw 'Repository hygiene check failed.'
    }

    Write-Host "`n[2/4] Git whitespace/diff check..."
    git diff --check
    if ($LASTEXITCODE -ne 0) {
        throw 'git diff --check failed.'
    }

    Write-Host "`n[3/4] Backend test suite..."
    Push-Location (Join-Path $RepoRoot 'backend')
    try {
        if (-not (Test-Path '.\vendor\autoload.php')) {
            throw 'Composer dependencies are missing. Run: composer install --no-interaction --prefer-dist'
        }

        php .\vendor\bin\phpunit --display-warnings --fail-on-warning
        if ($LASTEXITCODE -ne 0) {
            throw 'Backend tests failed or produced warnings.'
        }
    }
    finally {
        Pop-Location
    }

    Write-Host "`n[4/4] Frontend/static smoke tests..."
    Get-ChildItem (Join-Path $RepoRoot 'frontend/tests/*.mjs') |
        Sort-Object Name |
        ForEach-Object {
            Write-Host "--- $($_.Name)"
            node $_.FullName

            if ($LASTEXITCODE -ne 0) {
                throw "Frontend test failed: $($_.Name)"
            }
        }

    Write-Host ''
    Write-Host '========================================'
    Write-Host ' Release check: PASS'
    Write-Host '========================================'
}
finally {
    Pop-Location
}
