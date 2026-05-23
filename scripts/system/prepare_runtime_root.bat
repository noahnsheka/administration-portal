@echo off
setlocal

set "RUNTIME_ROOT=%~1"
if "%RUNTIME_ROOT%"=="" if defined ADMINISTRATION_RUNTIME_ROOT set "RUNTIME_ROOT=%ADMINISTRATION_RUNTIME_ROOT%"
if "%RUNTIME_ROOT%"=="" if defined XAMPP_ROOT set "RUNTIME_ROOT=%XAMPP_ROOT%"
if "%RUNTIME_ROOT%"=="" exit /b 0

for %%I in ("%RUNTIME_ROOT%") do set "RUNTIME_ROOT=%%~fI"

if not exist "%RUNTIME_ROOT%\apache\bin\httpd.exe" exit /b 0
if not exist "%RUNTIME_ROOT%\mysql\bin\mysqld.exe" exit /b 0

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0prepare_runtime_root.ps1" "%RUNTIME_ROOT%"

if errorlevel 1 exit /b 1
exit /b 0