@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"
set "APP_ENTRY_FILE=setup.php"

call "%SCRIPT_DIR%launch-installed.bat"
exit /b %ERRORLEVEL%