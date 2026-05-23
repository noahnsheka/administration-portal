@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
cd /d "%SCRIPT_DIR%"
set "ADMINISTRATION_INSTALLED_CONTEXT=1"
set "INSTALLED_RUNTIME_PREPARER=%SCRIPT_DIR%scripts\system\prepare_installed_runtime.bat"
set "RUNTIME_PREPARER=%SCRIPT_DIR%scripts\system\prepare_runtime_root.bat"

if not defined ADMINISTRATION_DATA_DIR set "ADMINISTRATION_DATA_DIR=%ProgramData%\Administration Suite"
if not exist "%ADMINISTRATION_DATA_DIR%" mkdir "%ADMINISTRATION_DATA_DIR%" >nul 2>&1

set "PROGRAMDATA_RUNTIME_ROOT=%ADMINISTRATION_DATA_DIR%\runtime\xampp"

if exist "%INSTALLED_RUNTIME_PREPARER%" (
	call "%INSTALLED_RUNTIME_PREPARER%" "%SCRIPT_DIR%"
	if errorlevel 1 exit /b %ERRORLEVEL%
)

if not exist "%PROGRAMDATA_RUNTIME_ROOT%\apache\bin\httpd.exe" goto stage_runtime
goto set_runtime_root

:stage_runtime
if exist "%SCRIPT_DIR%runtime\xampp\apache\bin\httpd.exe" robocopy "%SCRIPT_DIR%runtime\xampp" "%PROGRAMDATA_RUNTIME_ROOT%" /MIR >nul
set "ROBOCODE=%ERRORLEVEL%"
if %ROBOCODE% GEQ 8 exit /b %ROBOCODE%

:set_runtime_root
set "ADMINISTRATION_RUNTIME_ROOT=%PROGRAMDATA_RUNTIME_ROOT%"

if exist "%ADMINISTRATION_RUNTIME_ROOT%\apache\bin\httpd.exe" if exist "%RUNTIME_PREPARER%" (
	call "%RUNTIME_PREPARER%" "%ADMINISTRATION_RUNTIME_ROOT%"
	if errorlevel 1 exit /b %ERRORLEVEL%
)

if exist "%ADMINISTRATION_RUNTIME_ROOT%\apache\bin\httpd.exe" goto launch_application

if exist "%SCRIPT_DIR%runtime\xampp\apache\bin\httpd.exe" set "ADMINISTRATION_RUNTIME_ROOT=%SCRIPT_DIR%runtime\xampp"
if not exist "%ADMINISTRATION_RUNTIME_ROOT%\apache\bin\httpd.exe" set "ADMINISTRATION_RUNTIME_ROOT="

:launch_application
call "%SCRIPT_DIR%launch.bat"
exit /b %ERRORLEVEL%