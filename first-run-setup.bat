@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"

if not exist "%SCRIPT_DIR%launch.bat" (
  echo launch.bat was not found in %SCRIPT_DIR%.
  pause
  exit /b 1
)

set "APP_ENTRY_FILE=setup.php"

if /I "%ADMINISTRATION_DRY_RUN%"=="1" (
  echo First-run launcher is configured to open %APP_ENTRY_FILE%
  exit /b 0
)

call "%SCRIPT_DIR%launch.bat"
exit /b %ERRORLEVEL%