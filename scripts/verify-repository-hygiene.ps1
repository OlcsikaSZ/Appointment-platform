$ErrorActionPreference = 'Stop'

$repoRoot = (git rev-parse --show-toplevel 2>$null)
if (-not $repoRoot) {
    throw 'A scriptet a .git mappát tartalmazó repository gyökerében futtasd.'
}

Set-Location $repoRoot
$trackedFiles = @(git ls-files)
$forbiddenPatterns = @(
    '(^|/)\.env($|\.)',
    '^backend/vendor/',
    '^backend/\.phpunit\.cache/',
    '^backend/bootstrap/cache/(?!\.gitkeep$)',
    '^backend/storage/framework/(cache|sessions|views)/(?!.*\.gitkeep$)',
    '^backend/storage/logs/(?!\.gitkeep$)',
    '^backend/storage/app/public/(businesses|services)/(?!\.gitkeep$)',
    '^uploads/(businesses|services)/(?!\.gitkeep$)',
    '\.(log|sqlite3?|dump|bak|zip)$',
    '\.sql$'
)

$allowedTrackedSql = 'backend/database/schema_mysql.sql'
$allowedTrackedEnv = @('backend/.env.example', 'backend/.env.testing.example')
$violations = foreach ($file in $trackedFiles) {
    if ($file -eq $allowedTrackedSql) { continue }
    if ($allowedTrackedEnv -contains $file) { continue }
    foreach ($pattern in $forbiddenPatterns) {
        if ($file -match $pattern) {
            $file
            break
        }
    }
}

if ($violations) {
    Write-Host 'Tiltott vagy runtime fájl maradt Gitben:' -ForegroundColor Red
    $violations | Sort-Object -Unique | ForEach-Object { Write-Host "  $_" }
    exit 1
}

$requiredEnvKeys = @(
    'APP_KEY', 'APP_URL', 'FRONTEND_URL', 'PUBLIC_APP_URL', 'APP_TIMEZONE',
    'DB_CONNECTION', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
    'QUEUE_CONNECTION', 'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
    'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS',
    'ADMIN_IDLE_TIMEOUT_MINUTES', 'ADMIN_TOKEN_LIFETIME_MINUTES',
    'CUSTOMER_TOKEN_LIFETIME_MINUTES', 'ADMIN_SEED_PASSWORD'
)
$example = Get-Content 'backend/.env.example' -Raw
$missingKeys = @($requiredEnvKeys | Where-Object { $example -notmatch "(?m)^$($_)=" })

if ($missingKeys) {
    Write-Host 'Hiányzó kulcsok a backend/.env.example fájlból:' -ForegroundColor Red
    $missingKeys | ForEach-Object { Write-Host "  $_" }
    exit 1
}

Write-Host 'Repository hygiene ellenőrzés: rendben.' -ForegroundColor Green
