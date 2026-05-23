@echo off
setlocal enabledelayedexpansion

if /I "%~1"=="--elevated" set "ADMINISTRATION_ELEVATED_LAUNCH=1"
if /I "%~1"=="--installed" set "ADMINISTRATION_INSTALLED_CONTEXT=1"

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"
set "PROGRAMDATA_CONFIG_DIR=%ProgramData%\Administration Suite"
set "PROGRAMDATA_RUNTIME_ENV=%PROGRAMDATA_CONFIG_DIR%\.env.runtime"
set "LOCAL_RUNTIME_ENV=%SCRIPT_DIR%.env.runtime"
set "SYNC_SCRIPT=%SCRIPT_DIR%scripts\system\sync_app_to_apache.bat"
set "ENV_LOADER=%SCRIPT_DIR%scripts\system\load_env.bat"
set "XAMPP_RESOLVER=%SCRIPT_DIR%scripts\system\resolve_xampp_env.bat"
set "RUNTIME_PREPARER=%SCRIPT_DIR%scripts\system\prepare_runtime_root.bat"
set "SERVER_RESOLVER=%SCRIPT_DIR%scripts\system\resolve_server_access_env.bat"
set "APACHE_ACCESS_CONFIG=%SCRIPT_DIR%scripts\system\configure_apache_access.bat"
set "WINDOWS_SERVER_ACCESS=%SCRIPT_DIR%scripts\system\configure_windows_server_access.bat"

if /I not "%ADMINISTRATION_INSTALLED_CONTEXT%"=="1" if not exist "%LOCAL_RUNTIME_ENV%" if exist "%PROGRAMDATA_RUNTIME_ENV%" (
  set "ADMINISTRATION_DATA_DIR=%PROGRAMDATA_CONFIG_DIR%"
  if exist "%PROGRAMDATA_CONFIG_DIR%\runtime\xampp\apache\bin\httpd.exe" set "ADMINISTRATION_RUNTIME_ROOT=%PROGRAMDATA_CONFIG_DIR%\runtime\xampp"
)

if exist "%ENV_LOADER%" call "%ENV_LOADER%" "%SCRIPT_DIR%" >nul 2>&1

if not defined APP_NAME set "APP_NAME=Administration Suite"
if not defined ADMINISTRATION_RUNTIME_ROOT set "ADMINISTRATION_RUNTIME_ROOT=%SCRIPT_DIR%runtime\xampp"
if not exist "%ADMINISTRATION_RUNTIME_ROOT%\apache\bin\httpd.exe" set "ADMINISTRATION_RUNTIME_ROOT="
if exist "%XAMPP_RESOLVER%" call "%XAMPP_RESOLVER%"
if defined ADMINISTRATION_RUNTIME_ROOT if /I "%XAMPP_ROOT%"=="%ADMINISTRATION_RUNTIME_ROOT%" if exist "%RUNTIME_PREPARER%" call "%RUNTIME_PREPARER%" "%ADMINISTRATION_RUNTIME_ROOT%"
if exist "%SERVER_RESOLVER%" call "%SERVER_RESOLVER%"

set "FIREWALL_RULE_NAME=Administration Suite School Server"
set "NEEDS_ADMIN_AUTO_APPLY=0"
if /I "!APP_SERVER_MODE!"=="school-server" set "NEEDS_ADMIN_AUTO_APPLY=1"
if "!NEEDS_ADMIN_AUTO_APPLY!"=="0" (
  call :firewall_rule_exists "!FIREWALL_RULE_NAME!"
  if not errorlevel 1 set "NEEDS_ADMIN_AUTO_APPLY=1"
)

if /I not "%ADMINISTRATION_DRY_RUN%"=="1" if "!NEEDS_ADMIN_AUTO_APPLY!"=="1" if /I not "!ADMINISTRATION_ELEVATED_LAUNCH!"=="1" (
  net session >nul 2>&1
  if errorlevel 1 (
    call :relaunch_as_admin
    set "ELEVATION_EXIT_CODE=!ERRORLEVEL!"
    if not "!ELEVATION_EXIT_CODE!"=="0" (
      echo Administrator approval was not granted, so school-server access could not be refreshed automatically.
      pause
    )
    exit /b !ELEVATION_EXIT_CODE!
  )
)

if not defined APP_FOLDER set "APP_FOLDER=administration"
if not defined APP_ENTRY_FILE set "APP_ENTRY_FILE=index.php"
if not defined APP_LOCAL_URL set "APP_LOCAL_URL=http://localhost/%APP_FOLDER%/%APP_ENTRY_FILE%"
if not defined APP_CLIENT_URL set "APP_CLIENT_URL=%APP_LOCAL_URL%"
if not defined APP_LOCAL_SETUP_URL set "APP_LOCAL_SETUP_URL=http://localhost/%APP_FOLDER%/setup.php"
if not defined APP_CLIENT_SETUP_URL set "APP_CLIENT_SETUP_URL=%APP_LOCAL_SETUP_URL%"
set "APP_URL=%APP_LOCAL_URL%"
set "APP_READY_URL=%APP_LOCAL_URL%"
set "READY_URL_ATTEMPTS=3"

if /I "%ADMINISTRATION_DRY_RUN%"=="1" (
  if "!NEEDS_ADMIN_AUTO_APPLY!"=="1" if /I not "!ADMINISTRATION_ELEVATED_LAUNCH!"=="1" echo Launcher will request administrator approval automatically to refresh Apache and Windows Firewall.
  echo Server mode: !APP_SERVER_MODE!
  echo Server bind address: !APP_SERVER_BIND_ADDRESS!
  echo School client URL: !APP_CLIENT_URL!
  echo School setup URL: !APP_CLIENT_SETUP_URL!
  echo Administration data directory: !ADMINISTRATION_DATA_DIR!
  echo Administration runtime root: !ADMINISTRATION_RUNTIME_ROOT!
  echo XAMPP root: !XAMPP_ROOT!
  echo Apache sync target: !APACHE_SYNC_TARGET!
  echo Launch target: !APP_URL!
  exit /b 0
)

if not defined XAMPP_ROOT (
  echo No bundled runtime or XAMPP installation could be found automatically.
  echo Install XAMPP or set XAMPP_ROOT in .env, .env.local, or .env.runtime.
  pause
  exit /b 1
)

if not exist "!XAMPP_ROOT!\apache\bin\httpd.exe" (
  echo XAMPP Apache was not found at !XAMPP_ROOT!.
  pause
  exit /b 1
)

if exist "%APACHE_ACCESS_CONFIG%" call "%APACHE_ACCESS_CONFIG%" "%SCRIPT_DIR%"
if exist "%WINDOWS_SERVER_ACCESS%" call "%WINDOWS_SERVER_ACCESS%" "%SCRIPT_DIR%"

if exist "!SYNC_SCRIPT!" (
  echo Syncing application files to XAMPP htdocs...
  call "!SYNC_SCRIPT!"
  if errorlevel 1 (
    echo Sync failed. Review the message above and fix the deployment path settings before launching.
    pause
    exit /b 1
  )
)

echo Starting MySQL...
if exist "!XAMPP_ROOT!\mysql_start.bat" (
  start "" /min "!XAMPP_ROOT!\mysql_start.bat"
)

timeout /t 2 /nobreak >nul

call :wait_for_http "!APP_READY_URL!" 1
if not errorlevel 1 goto apache_already_running

echo Validating Apache configuration...
call :validate_apache_config
if errorlevel 1 (
  echo.
  echo Apache configuration validation failed before startup.
  call :show_apache_error_log_tail
  echo.
  pause
  exit /b 1
)

echo Starting Apache...
if exist "!XAMPP_ROOT!\apache_start.bat" (
  start "" /min "!XAMPP_ROOT!\apache_start.bat"
) else (
  start "" /min "!XAMPP_ROOT!\apache\bin\httpd.exe"
)

timeout /t 3 /nobreak >nul

goto apache_ready

:apache_already_running
echo Apache is already responding.
set "READY_URL_ATTEMPTS=1"

:apache_ready

echo Verifying the local application URL...
call :wait_for_http "!APP_READY_URL!" !READY_URL_ATTEMPTS!
if errorlevel 1 (
  echo The application URL did not respond immediately, but the browser will open now.
)

echo Launching %APP_NAME%...
start "" "%APP_URL%"

echo.
echo %APP_NAME% is running.
echo Open %APP_URL% in your browser.
if /I "!APP_SERVER_MODE!"=="school-server" echo School client computers can connect at !APP_CLIENT_URL!
if exist "!APACHE_SYNC_TARGET!\setup.php" echo First-run setup remains available at !APP_LOCAL_SETUP_URL!
echo.
pause

goto :eof

:validate_apache_config
pushd "!XAMPP_ROOT!" >nul
apache\bin\httpd.exe -t
set "APACHE_TEST_EXIT_CODE=%ERRORLEVEL%"
popd >nul
exit /b !APACHE_TEST_EXIT_CODE!

:show_apache_error_log_tail
if not exist "!XAMPP_ROOT!\apache\logs\error.log" exit /b 0
echo Recent Apache error log output:
set "APACHE_ERROR_LOG=!XAMPP_ROOT!\apache\logs\error.log"
powershell -NoProfile -Command "Get-Content -Path $env:APACHE_ERROR_LOG -Tail 20"
exit /b 0

:firewall_rule_exists
netsh advfirewall firewall show rule name="%~1" | findstr /I /C:"Rule Name:" >nul 2>&1
exit /b %ERRORLEVEL%

:relaunch_as_admin
set "TEMP_LAUNCHER_PATH=%TEMP%\administration-elevated-launch-%RANDOM%%RANDOM%.cmd"

(
  echo @echo off
  echo setlocal enabledelayedexpansion
  echo set "ADMINISTRATION_ELEVATED_LAUNCH=1"
  for %%V in (ADMINISTRATION_DATA_DIR ADMINISTRATION_RUNTIME_ROOT ADMINISTRATION_INSTALLED_CONTEXT APP_ENTRY_FILE ADMINISTRATION_DRY_RUN APP_NAME XAMPP_ROOT APP_FOLDER APACHE_SYNC_TARGET APP_SERVER_MODE APP_SERVER_PORT APP_SERVER_PUBLIC_HOST APP_SERVER_BIND_ADDRESS APP_SERVER_OPEN_FIREWALL) do (
    if defined %%V echo set "%%V=!%%V!"
  )
  echo call "%~f0" --elevated
  echo set "LAUNCH_EXIT_CODE=%%ERRORLEVEL%%"
  echo del "%%~f0" ^>nul 2^>^&1
  echo exit /b %%LAUNCH_EXIT_CODE%%
) > "!TEMP_LAUNCHER_PATH!"

set "ELEVATED_LAUNCH_SCRIPT=!TEMP_LAUNCHER_PATH!"
powershell -NoProfile -Command "$launchScript = $env:ELEVATED_LAUNCH_SCRIPT; try { [void](Start-Process -FilePath $env:ComSpec -ArgumentList ('/c ""' + $launchScript + '""') -Verb RunAs -ErrorAction Stop); exit 0 } catch { exit 1223 }"
set "START_PROCESS_EXIT_CODE=%ERRORLEVEL%"

if not "!START_PROCESS_EXIT_CODE!"=="0" if exist "!TEMP_LAUNCHER_PATH!" del "!TEMP_LAUNCHER_PATH!" >nul 2>&1
exit /b !START_PROCESS_EXIT_CODE!

:wait_for_http
set "WAIT_TARGET_URL=%~1"
set "WAIT_ATTEMPTS=%~2"
if "%WAIT_ATTEMPTS%"=="" set "WAIT_ATTEMPTS=20"

for /L %%I in (1,1,%WAIT_ATTEMPTS%) do (
  powershell -NoProfile -Command "$ErrorActionPreference = 'Stop'; try { $response = Invoke-WebRequest -Uri $env:WAIT_TARGET_URL -UseBasicParsing -MaximumRedirection 0 -TimeoutSec 2; if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 500) { exit 0 } exit 1 } catch { if ($_.Exception.Response -and $_.Exception.Response.StatusCode.value__ -ge 200 -and $_.Exception.Response.StatusCode.value__ -lt 500) { exit 0 } exit 1 }" >nul 2>&1
  if not errorlevel 1 exit /b 0
  timeout /t 1 /nobreak >nul
)

exit /b 1
