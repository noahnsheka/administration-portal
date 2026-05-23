@echo off
setlocal

set "PROJECT_ROOT=%~1"
if "%PROJECT_ROOT%"=="" set "PROJECT_ROOT=%~dp0\..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"

if not defined ADMINISTRATION_DATA_DIR set "ADMINISTRATION_DATA_DIR=%ProgramData%\Administration Suite"
if not exist "%ADMINISTRATION_DATA_DIR%" mkdir "%ADMINISTRATION_DATA_DIR%" >nul 2>&1

set "SOURCE_RUNTIME_ROOT=%PROJECT_ROOT%\runtime\xampp"
set "TARGET_RUNTIME_ROOT=%ADMINISTRATION_DATA_DIR%\runtime\xampp"

if exist "%SOURCE_RUNTIME_ROOT%\apache\bin\httpd.exe" if not exist "%TARGET_RUNTIME_ROOT%\apache\bin\httpd.exe" goto stage_runtime

exit /b 0

:stage_runtime
robocopy "%SOURCE_RUNTIME_ROOT%" "%TARGET_RUNTIME_ROOT%" /MIR >nul
set "ROBOCODE=%ERRORLEVEL%"
if %ROBOCODE% GEQ 8 exit /b %ROBOCODE%

exit /b 0