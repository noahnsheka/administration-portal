@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
for %%I in ("%SCRIPT_DIR%.") do set "SCRIPT_DIR=%%~fI"
cd /d "%SCRIPT_DIR%"
for %%I in ("%SCRIPT_DIR%\..") do set "PARENT_DIR=%%~fI"
set "OUTPUT_DIR=%PARENT_DIR%\administration-portable-package"
set "RUNTIME_SOURCE=%SCRIPT_DIR%\dist\runtime\xampp"
set "OUTPUT_RUNTIME=%OUTPUT_DIR%\runtime\xampp"
set "STAGE_RUNTIME_SCRIPT=%SCRIPT_DIR%\scripts\system\stage_desktop_runtime.bat"
set "CLIENT_APP_FOLDER=administration"
set "CLIENT_DB_NAME=administration_suite"

if exist "%SCRIPT_DIR%\.env.example" call :read_env_value "%SCRIPT_DIR%\.env.example" "APP_FOLDER" CLIENT_APP_FOLDER
if exist "%SCRIPT_DIR%\.env.example" call :read_env_value "%SCRIPT_DIR%\.env.example" "DB_NAME" CLIENT_DB_NAME

if not exist "%RUNTIME_SOURCE%\apache\bin\httpd.exe" if exist "%STAGE_RUNTIME_SCRIPT%" (
  echo Staging bundled runtime from the local XAMPP installation...
  call "%STAGE_RUNTIME_SCRIPT%" "%SCRIPT_DIR%"
  if errorlevel 1 exit /b %ERRORLEVEL%
)

if not exist "%RUNTIME_SOURCE%\apache\bin\httpd.exe" (
  echo Portable package export requires a bundled runtime at:
  echo %RUNTIME_SOURCE%
  echo.
  echo Install XAMPP on this build machine or pre-stage the runtime, then rerun this script.
  exit /b 1
)

if exist "%OUTPUT_DIR%" rmdir /s /q "%OUTPUT_DIR%" >nul 2>&1
mkdir "%OUTPUT_DIR%" >nul 2>&1

robocopy "%SCRIPT_DIR%" "%OUTPUT_DIR%" /MIR /XD ".git" ".vscode" "node_modules" "dist" "installer" "_internal" /XF ".env" ".env.local" ".env.runtime" "desktop-installer.md" "package-client.md" "README.md" "package-client.bat" "package-desktop-installer.bat" "launch-installed.bat" "launch-installed.vbs" "first-run-setup-installed.bat" "first-run-setup-installed.vbs" >nul
set "ROBOCODE=%ERRORLEVEL%"

if %ROBOCODE% GEQ 8 (
  echo Portable package export failed. Robocopy exit code: %ROBOCODE%
  exit /b %ROBOCODE%
)

robocopy "%RUNTIME_SOURCE%" "%OUTPUT_RUNTIME%" /MIR >nul
set "ROBOCODE=%ERRORLEVEL%"

if %ROBOCODE% GEQ 8 (
  echo Bundled runtime export failed. Robocopy exit code: %ROBOCODE%
  exit /b %ROBOCODE%
)

if exist "%OUTPUT_DIR%\.env" del /f /q "%OUTPUT_DIR%\.env" >nul 2>&1
if exist "%OUTPUT_DIR%\.env.local" del /f /q "%OUTPUT_DIR%\.env.local" >nul 2>&1
if exist "%OUTPUT_DIR%\.env.runtime" del /f /q "%OUTPUT_DIR%\.env.runtime" >nul 2>&1

if exist "%SCRIPT_DIR%\.env.example" copy /Y "%SCRIPT_DIR%\.env.example" "%OUTPUT_DIR%\.env" >nul

call :sanitize_packaged_state
if errorlevel 1 exit /b %ERRORLEVEL%

call :remove_internal_client_files
if errorlevel 1 exit /b %ERRORLEVEL%

echo Portable package created at:
echo %OUTPUT_DIR%
echo.
echo Bundled XAMPP runtime copied to:
echo %OUTPUT_RUNTIME%
echo.
echo Next step: give that folder to the client and have them run first-run-setup.bat on first run.
echo The package was exported without local .env, .env.local, or .env.runtime secrets.
echo No separate XAMPP installation is required on the client computer for this package.
exit /b 0

:read_env_value
for /f "usebackq tokens=1* delims==" %%A in (`findstr /r /b /c:"%~2=" "%~1"`) do (
  if /I "%%~A"=="%~2" set "%~3=%%~B"
)
exit /b 0

:sanitize_packaged_state
if exist "%OUTPUT_RUNTIME%\htdocs\%CLIENT_APP_FOLDER%" rmdir /s /q "%OUTPUT_RUNTIME%\htdocs\%CLIENT_APP_FOLDER%" >nul 2>&1

if exist "%OUTPUT_RUNTIME%\mysql\data" call :prune_mysql_data "%OUTPUT_RUNTIME%\mysql\data"

for %%D in (
  "%OUTPUT_RUNTIME%\apache\logs"
  "%OUTPUT_RUNTIME%\php\logs"
  "%OUTPUT_RUNTIME%\tmp"
) do if exist "%%~fD" call :clear_directory_contents "%%~fD"

for %%F in (
  "%OUTPUT_RUNTIME%\mysql\data\*.err"
  "%OUTPUT_RUNTIME%\mysql\data\*.pid"
  "%OUTPUT_RUNTIME%\mysql\data\ibtmp1"
  "%OUTPUT_RUNTIME%\mysql\data\ib_buffer_pool"
  "%OUTPUT_RUNTIME%\mysql\data\aria_log.00000001"
  "%OUTPUT_RUNTIME%\mysql\data\aria_log_control"
  "%OUTPUT_RUNTIME%\mysql\data\multi-master.info"
) do if exist "%%~fF" del /f /q "%%~fF" >nul 2>&1

exit /b 0

:remove_internal_client_files
for %%D in (
  "%OUTPUT_DIR%\installer"
  "%OUTPUT_DIR%\_internal"
) do if exist "%%~fD" rmdir /s /q "%%~fD" >nul 2>&1

for %%F in (
  "%OUTPUT_DIR%\desktop-installer.md"
  "%OUTPUT_DIR%\package-client.md"
  "%OUTPUT_DIR%\README.md"
  "%OUTPUT_DIR%\package-client.bat"
  "%OUTPUT_DIR%\package-desktop-installer.bat"
  "%OUTPUT_DIR%\launch-installed.bat"
  "%OUTPUT_DIR%\launch-installed.vbs"
  "%OUTPUT_DIR%\first-run-setup-installed.bat"
  "%OUTPUT_DIR%\first-run-setup-installed.vbs"
) do if exist "%%~fF" del /f /q "%%~fF" >nul 2>&1

exit /b 0

:prune_mysql_data
for /d %%D in ("%~1\*") do call :prune_mysql_data_dir "%%~fD"
exit /b 0

:clear_directory_contents
for /d %%D in ("%~1\*") do rmdir /s /q "%%~fD" >nul 2>&1
for %%F in ("%~1\*") do if exist "%%~fF" del /f /q "%%~fF" >nul 2>&1
exit /b 0

:prune_mysql_data_dir
set "DATA_DIR_NAME=%~nx1"
if /I "%DATA_DIR_NAME%"=="mysql" exit /b 0
if /I "%DATA_DIR_NAME%"=="performance_schema" exit /b 0
if /I "%DATA_DIR_NAME%"=="phpmyadmin" exit /b 0
if /I "%DATA_DIR_NAME%"=="repair-backups" exit /b 0
if /I "%DATA_DIR_NAME%"=="sys" exit /b 0
if /I "%DATA_DIR_NAME%"=="test" exit /b 0
if /I "%DATA_DIR_NAME%"=="%CLIENT_DB_NAME%" rmdir /s /q "%~1" >nul 2>&1 & exit /b 0

rmdir /s /q "%~1" >nul 2>&1
exit /b 0
