$ErrorActionPreference = 'Stop'

$BackendRoot = Split-Path -Parent $PSScriptRoot
Set-Location $BackendRoot

php artisan schedule:work