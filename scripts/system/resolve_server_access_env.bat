@echo off

if not defined APP_FOLDER set "APP_FOLDER=administration"
if not defined APP_ENTRY_FILE set "APP_ENTRY_FILE=index.php"
if not defined APP_SERVER_MODE set "APP_SERVER_MODE=desktop"
if /I not "%APP_SERVER_MODE%"=="school-server" set "APP_SERVER_MODE=desktop"

if not defined APP_SERVER_PORT set "APP_SERVER_PORT=80"
set /a APP_SERVER_PORT_CHECK=%APP_SERVER_PORT% >nul 2>&1
if errorlevel 1 set "APP_SERVER_PORT=80"
if %APP_SERVER_PORT% LSS 1 set "APP_SERVER_PORT=80"
if %APP_SERVER_PORT% GTR 65535 set "APP_SERVER_PORT=80"

if /I "%APP_SERVER_MODE%"=="school-server" if /I "%APP_SERVER_PUBLIC_HOST%"=="localhost" set "APP_SERVER_PUBLIC_HOST="
if /I "%APP_SERVER_MODE%"=="school-server" if "%APP_SERVER_PUBLIC_HOST%"=="127.0.0.1" set "APP_SERVER_PUBLIC_HOST="
if /I "%APP_SERVER_MODE%"=="school-server" if /I "%APP_SERVER_PUBLIC_HOST%"=="::1" set "APP_SERVER_PUBLIC_HOST="
if not defined APP_SERVER_PUBLIC_HOST if /I "%APP_SERVER_MODE%"=="school-server" call :detect_public_host
if not defined APP_SERVER_PUBLIC_HOST set "APP_SERVER_PUBLIC_HOST=localhost"

set "APP_SERVER_BIND_ADDRESS=127.0.0.1"
if /I "%APP_SERVER_MODE%"=="school-server" set "APP_SERVER_BIND_ADDRESS=0.0.0.0"

call :build_url APP_LOCAL_URL localhost "%APP_ENTRY_FILE%"
call :build_url APP_CLIENT_URL "%APP_SERVER_PUBLIC_HOST%" "%APP_ENTRY_FILE%"
call :build_url APP_LOCAL_SETUP_URL localhost setup.php
call :build_url APP_CLIENT_SETUP_URL "%APP_SERVER_PUBLIC_HOST%" setup.php
exit /b 0

:build_url
set "TARGET_VAR=%~1"
set "TARGET_HOST=%~2"
set "TARGET_FILE=%~3"
set "PORT_FRAGMENT="
if not "%APP_SERVER_PORT%"=="80" set "PORT_FRAGMENT=:%APP_SERVER_PORT%"
set "%TARGET_VAR%=http://%TARGET_HOST%%PORT_FRAGMENT%/%APP_FOLDER%/%TARGET_FILE%"
exit /b 0

:detect_public_host
for /f "usebackq delims=" %%I in (`powershell -NoProfile -Command "$addresses = @(); try { $addresses = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254*' -and $_.IPAddress -ne '0.0.0.0' } | Select-Object -ExpandProperty IPAddress -Unique } catch { }; if (-not $addresses) { $addresses = [System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()) | Where-Object { $_.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork -and $_.IPAddressToString -notlike '127.*' -and $_.IPAddressToString -notlike '169.254*' -and $_.IPAddressToString -ne '0.0.0.0' } | Select-Object -ExpandProperty IPAddressToString -Unique }; if ($addresses) { $addresses[0] }"`) do (
  if not defined APP_SERVER_PUBLIC_HOST set "APP_SERVER_PUBLIC_HOST=%%I"
)

if not defined APP_SERVER_PUBLIC_HOST if defined COMPUTERNAME set "APP_SERVER_PUBLIC_HOST=%COMPUTERNAME%"
exit /b 0