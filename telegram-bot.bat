@echo off
rem Starts the Telegram bot (bin\telegram-bot.php) with Laragon's PHP.
rem Double-click it, or put a shortcut to it in the Startup folder (Win+R, shell:startup).
cd /d "%~dp0"
set "PHP=php"
for /d %%D in (C:\laragon\bin\php\php-*) do if exist "%%D\php.exe" set "PHP=%%D\php.exe"
title Charity shop Telegram bot
:loop
"%PHP%" bin\telegram-bot.php
echo The bot stopped. Restarting in 10 seconds (close this window to stop it)...
timeout /t 10 /nobreak >nul
goto loop
