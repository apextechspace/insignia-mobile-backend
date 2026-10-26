@echo off
cd /d "%~dp0"
echo Starting Laravel server on 192.168.8.199...
"C:\xampp\php\php.exe" artisan serve --host=192.168.8.199 --port=8000
pause
