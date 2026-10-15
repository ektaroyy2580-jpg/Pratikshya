@echo off
REM WaitWise launcher: uses the local php.ini in ..\phpconf
set PHPRC=%~dp0..\phpconf
cd /d %~dp0
php artisan migrate:fresh --seed --force
php artisan serve --port=8003
