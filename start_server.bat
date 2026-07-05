@echo off
cd /d "%~dp0"
echo Starting Laravel server on 192.168.1.120...
"C:\xampp\php\php.exe" artisan serve --host=192.168.1.120 --port=8000
pause
