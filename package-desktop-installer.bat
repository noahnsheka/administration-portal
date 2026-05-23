@echo off
setlocal

set "SCRIPT_DIR=%~dp0"
for %%I in ("%SCRIPT_DIR%.") do set "SCRIPT_DIR=%%~fI"
cd /d "%SCRIPT_DIR%"

set "APP_VERSION=%~1"
if "%APP_VERSION%"=="" set "APP_VERSION=1.0.0"

set "RUNTIME_ROOT=%SCRIPT_DIR%\dist\runtime\xampp"
set "STAGE_RUNTIME_SCRIPT=%SCRIPT_DIR%\scripts\system\stage_desktop_runtime.bat"

if not exist "%RUNTIME_ROOT%\apache\bin\httpd.exe" if exist "%STAGE_RUNTIME_SCRIPT%" (
  echo Staging desktop runtime from the local XAMPP installation...
  call "%STAGE_RUNTIME_SCRIPT%" "%SCRIPT_DIR%"
  if errorlevel 1 exit /b %ERRORLEVEL%
)

if not exist "%RUNTIME_ROOT%\apache\bin\httpd.exe" (
  echo Desktop installer build requires a bundled runtime at:
  echo %RUNTIME_ROOT%
  echo.
  echo Put a portable XAMPP-compatible runtime there first, then rerun this script.
  exit /b 1
)

call :find_iscc
if not defined ISCC_EXE (
  echo Inno Setup compiler ^(ISCC.exe^) was not found.
  echo Install Inno Setup 6 or add ISCC.exe to PATH, then rerun this script.
  exit /b 1
)

"%ISCC_EXE%" "/DAppVersion=%APP_VERSION%" "%SCRIPT_DIR%\installer\AdministrationSuite.iss"
exit /b %ERRORLEVEL%

:find_iscc
for %%I in (ISCC.exe) do if not "%%~$PATH:I"=="" set "ISCC_EXE=%%~$PATH:I"
if defined ISCC_EXE exit /b 0

if defined ProgramFiles(x86) if exist "%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe" set "ISCC_EXE=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe"
if defined ISCC_EXE exit /b 0

if defined ProgramFiles if exist "%ProgramFiles%\Inno Setup 6\ISCC.exe" set "ISCC_EXE=%ProgramFiles%\Inno Setup 6\ISCC.exe"
exit /b 0