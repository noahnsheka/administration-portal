@echo off
setlocal enabledelayedexpansion

set "SOURCE_DIR=%~dp0\..\.."
for %%I in ("%SOURCE_DIR%") do set "SOURCE_DIR=%%~fI"
if exist "%SOURCE_DIR%\scripts\system\load_env.bat" call "%SOURCE_DIR%\scripts\system\load_env.bat" "%SOURCE_DIR%" >nul 2>&1
if exist "%SOURCE_DIR%\scripts\system\resolve_xampp_env.bat" call "%SOURCE_DIR%\scripts\system\resolve_xampp_env.bat"

if not defined APP_FOLDER set "APP_FOLDER=administration"
if not defined XAMPP_ROOT (
  echo XAMPP could not be found automatically.
  echo Install XAMPP or set XAMPP_ROOT in .env, .env.local, or .env.runtime.
  exit /b 1
)

if not defined APACHE_SYNC_TARGET set "APACHE_SYNC_TARGET=%XAMPP_ROOT%\htdocs\%APP_FOLDER%"
set "TARGET_DIR=%APACHE_SYNC_TARGET%"

if not exist "%TARGET_DIR%" mkdir "%TARGET_DIR%"

if not exist "!TARGET_DIR!" (
  echo Target directory could not be created at !TARGET_DIR!
  exit /b 1
)

robocopy "%SOURCE_DIR%" "%TARGET_DIR%" /MIR /XD ".git" ".vscode" "node_modules" "dist" "runtime" /XF ".env" ".env.local" ".env.runtime" >nul
set "ROBOCODE=%ERRORLEVEL%"

if %ROBOCODE% GEQ 8 (
  echo Sync failed. Robocopy exit code: %ROBOCODE%
  exit /b %ROBOCODE%
)

if not exist "!TARGET_DIR!\.env" if exist "!SOURCE_DIR!\.env.example" copy /Y "!SOURCE_DIR!\.env.example" "!TARGET_DIR!\.env" >nul

echo Sync completed to !TARGET_DIR!
echo Existing target environment files were preserved.
exit /b 0
