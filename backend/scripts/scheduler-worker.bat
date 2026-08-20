@echo off
setlocal

cd /d "%~dp0.."

php artisan schedule:work

endlocal