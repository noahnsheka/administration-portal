@echo off
setlocal

set "PROJECT_ROOT=%~1"
if "%PROJECT_ROOT%"=="" set "PROJECT_ROOT=%~dp0\..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"

if exist "%PROJECT_ROOT%\scripts\system\load_env.bat" call "%PROJECT_ROOT%\scripts\system\load_env.bat" "%PROJECT_ROOT%" >nul 2>&1
if exist "%PROJECT_ROOT%\scripts\system\resolve_server_access_env.bat" call "%PROJECT_ROOT%\scripts\system\resolve_server_access_env.bat" >nul 2>&1

set "RULE_NAME=Administration Suite School Server"

net session >nul 2>&1
if errorlevel 1 exit /b 0

if /I not "%APP_SERVER_MODE%"=="school-server" (
  netsh advfirewall firewall delete rule name="%RULE_NAME%" >nul 2>&1
  exit /b 0
)

if /I not "%APP_SERVER_OPEN_FIREWALL%"=="1" exit /b 0

netsh advfirewall firewall delete rule name="%RULE_NAME%" >nul 2>&1
netsh advfirewall firewall add rule name="%RULE_NAME%" dir=in action=allow protocol=TCP localport=%APP_SERVER_PORT% profile=private >nul 2>&1
exit /b 0