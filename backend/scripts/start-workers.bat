@echo off
setlocal

cd /d "%~dp0.."

echo Queue worker inditasa...
start "Appointment Queue Worker" cmd /k call "%~dp0queue-worker.bat"

echo Scheduler worker inditasa...
start "Appointment Scheduler Worker" cmd /k call "%~dp0scheduler-worker.bat"

echo.
echo Mindket hatterfolyamat elindult kulon ablakban.
echo A 24/2 oras emlekeztetokhoz mindket ablak maradjon futasban.

endlocal
