@echo off

set "PROJECT_ROOT=%~1"
if "%PROJECT_ROOT%"=="" set "PROJECT_ROOT=%~dp0\..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"

call :load_file "%PROJECT_ROOT%\.env"
call :load_file "%PROJECT_ROOT%\.env.local"

if defined ADMINISTRATION_DATA_DIR goto load_data_runtime

call :load_file "%PROJECT_ROOT%\.env.runtime"
exit /b 0

:load_data_runtime
call :load_file "%ADMINISTRATION_DATA_DIR%\.env.runtime"
exit /b 0

:load_file
if not exist "%~1" exit /b 0

for /f "usebackq tokens=1* delims==" %%A in (`findstr /r /v "^[ ]*[#;]" "%~1"`) do (
  if /I "%%~A"=="ADMINISTRATION_DATA_DIR" if not defined ADMINISTRATION_DATA_DIR set "%%~A=%%~B"
  if /I "%%~A"=="ADMINISTRATION_RUNTIME_ROOT" if not defined ADMINISTRATION_RUNTIME_ROOT set "%%~A=%%~B"
  if /I not "%%~A"=="ADMINISTRATION_DATA_DIR" if /I not "%%~A"=="ADMINISTRATION_RUNTIME_ROOT" if not "%%~A"=="" set "%%~A=%%~B"
)

exit /b 0
