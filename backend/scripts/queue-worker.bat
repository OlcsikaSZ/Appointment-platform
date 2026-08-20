@echo off
setlocal

cd /d "%~dp0.."

php artisan queue:work database --queue=emails,default --sleep=3 --tries=5 --backoff=60 --timeout=90 --max-time=3600

endlocal