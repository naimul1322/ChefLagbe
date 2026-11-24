@echo off
cd /d "%~dp0"
if not exist vendor\autoload.php (
  echo Run composer install first. See README.md.
  pause
  exit /b 1
)
if not exist .env (
  echo Create .env and configure MySQL first. See README.md.
  pause
  exit /b 1
)
php artisan serve --host=127.0.0.1 --port=8000
