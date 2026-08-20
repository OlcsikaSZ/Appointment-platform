@echo off
setlocal
cd /d "%~dp0.."
del /q "app\Models\CustomerMagicLink.php" 2>nul
del /q "app\Mail\CustomerMagicLinkMail.php" 2>nul
del /q "resources\views\emails\customer-magic-link.blade.php" 2>nul
echo A regi magic-link fajlok eltavolitasa kesz.
endlocal
