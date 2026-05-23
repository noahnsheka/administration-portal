@echo off
setlocal

set "PROJECT_ROOT=%~1"
if "%PROJECT_ROOT%"=="" set "PROJECT_ROOT=%~dp0\..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"

if exist "%PROJECT_ROOT%\scripts\system\load_env.bat" call "%PROJECT_ROOT%\scripts\system\load_env.bat" "%PROJECT_ROOT%" >nul 2>&1
if exist "%PROJECT_ROOT%\scripts\system\resolve_xampp_env.bat" call "%PROJECT_ROOT%\scripts\system\resolve_xampp_env.bat" >nul 2>&1
if exist "%PROJECT_ROOT%\scripts\system\resolve_server_access_env.bat" call "%PROJECT_ROOT%\scripts\system\resolve_server_access_env.bat" >nul 2>&1

if not defined XAMPP_ROOT exit /b 0

set "HTTPD_CONF=%XAMPP_ROOT%\apache\conf\httpd.conf"
if not exist "%HTTPD_CONF%" exit /b 0

set "LISTEN_VALUE=Listen 127.0.0.1:%APP_SERVER_PORT%"
if /I "%APP_SERVER_MODE%"=="school-server" set "LISTEN_VALUE=Listen %APP_SERVER_PORT%"
set "SERVER_NAME_VALUE=ServerName localhost:%APP_SERVER_PORT%"

powershell -NoProfile -Command "$path = $env:HTTPD_CONF; $contents = [System.IO.File]::ReadAllText($path); $updated = $contents; $listenRegex = [regex]::new('(?m)^\s*Listen\s+.+$'); $listenMatch = $listenRegex.Match($updated); if ($listenMatch.Success) { $updated = $updated.Remove($listenMatch.Index, $listenMatch.Length).Insert($listenMatch.Index, $env:LISTEN_VALUE) } else { $updated = $env:LISTEN_VALUE + [Environment]::NewLine + $updated }; $serverNameRegex = [regex]::new('(?m)^\s*ServerName\s+.+$'); $serverNameMatch = $serverNameRegex.Match($updated); if ($serverNameMatch.Success) { $updated = $updated.Remove($serverNameMatch.Index, $serverNameMatch.Length).Insert($serverNameMatch.Index, $env:SERVER_NAME_VALUE) } else { $updated = $updated.TrimEnd() + [Environment]::NewLine + $env:SERVER_NAME_VALUE + [Environment]::NewLine }; if ($updated -ne $contents) { [System.IO.File]::WriteAllText($path, $updated, [System.Text.UTF8Encoding]::new($false)) }" >nul 2>&1
exit /b 0