@echo off
setlocal

set "PROJECT_ROOT=%~1"
if "%PROJECT_ROOT%"=="" set "PROJECT_ROOT=%~dp0\..\.."
for %%I in ("%PROJECT_ROOT%") do set "PROJECT_ROOT=%%~fI"
set "TARGET_ROOT=%PROJECT_ROOT%\dist\runtime\xampp"

if exist "%PROJECT_ROOT%\scripts\system\load_env.bat" call "%PROJECT_ROOT%\scripts\system\load_env.bat" "%PROJECT_ROOT%" >nul 2>&1

set "ADMINISTRATION_RUNTIME_ROOT="
if defined XAMPP_ROOT if /I "%XAMPP_ROOT%"=="%TARGET_ROOT%" set "XAMPP_ROOT="

if exist "%PROJECT_ROOT%\scripts\system\resolve_xampp_env.bat" call "%PROJECT_ROOT%\scripts\system\resolve_xampp_env.bat" >nul 2>&1

if not defined XAMPP_ROOT (
  echo A local XAMPP runtime could not be detected for desktop packaging.
  exit /b 1
)

if defined XAMPP_ROOT if /I "%XAMPP_ROOT%"=="%TARGET_ROOT%" set "XAMPP_ROOT="

if not exist "%XAMPP_ROOT%\apache\bin\httpd.exe" (
  echo XAMPP Apache was not found at %XAMPP_ROOT%.
  exit /b 1
)

if exist "%TARGET_ROOT%" rmdir /s /q "%TARGET_ROOT%"

mkdir "%TARGET_ROOT%" >nul 2>&1
mkdir "%TARGET_ROOT%\htdocs" >nul 2>&1
mkdir "%TARGET_ROOT%\tmp" >nul 2>&1
mkdir "%TARGET_ROOT%\mysql\data" >nul 2>&1

call :copy_dir "%XAMPP_ROOT%\apache" "%TARGET_ROOT%\apache"
call :copy_dir "%XAMPP_ROOT%\php" "%TARGET_ROOT%\php"

if exist "%XAMPP_ROOT%\sendmail" call :copy_dir "%XAMPP_ROOT%\sendmail" "%TARGET_ROOT%\sendmail"
if exist "%XAMPP_ROOT%\cgi-bin" call :copy_dir "%XAMPP_ROOT%\cgi-bin" "%TARGET_ROOT%\cgi-bin"

call :copy_dir "%XAMPP_ROOT%\mysql\bin" "%TARGET_ROOT%\mysql\bin"
call :copy_dir "%XAMPP_ROOT%\mysql\share" "%TARGET_ROOT%\mysql\share"
call :copy_dir "%XAMPP_ROOT%\mysql\scripts" "%TARGET_ROOT%\mysql\scripts"

if exist "%XAMPP_ROOT%\mysql\backup" call :copy_dir "%XAMPP_ROOT%\mysql\backup" "%TARGET_ROOT%\mysql\backup"

call :copy_file "%XAMPP_ROOT%\apache_start.bat" "%TARGET_ROOT%\apache_start.bat"
call :copy_file "%XAMPP_ROOT%\apache_stop.bat" "%TARGET_ROOT%\apache_stop.bat"
call :copy_file "%XAMPP_ROOT%\mysql_start.bat" "%TARGET_ROOT%\mysql_start.bat"
call :copy_file "%XAMPP_ROOT%\mysql_stop.bat" "%TARGET_ROOT%\mysql_stop.bat"
call :copy_file "%XAMPP_ROOT%\xampp-control.exe" "%TARGET_ROOT%\xampp-control.exe"
call :copy_file "%XAMPP_ROOT%\xampp-control.ini" "%TARGET_ROOT%\xampp-control.ini"
call :copy_file "%XAMPP_ROOT%\properties.ini" "%TARGET_ROOT%\properties.ini"
call :copy_file "%XAMPP_ROOT%\test_php.bat" "%TARGET_ROOT%\test_php.bat"

call :copy_file "%XAMPP_ROOT%\mysql\data\ibdata1" "%TARGET_ROOT%\mysql\data\ibdata1"
call :copy_file "%XAMPP_ROOT%\mysql\data\ibtmp1" "%TARGET_ROOT%\mysql\data\ibtmp1"
call :copy_file "%XAMPP_ROOT%\mysql\data\ib_buffer_pool" "%TARGET_ROOT%\mysql\data\ib_buffer_pool"
call :copy_file "%XAMPP_ROOT%\mysql\data\ib_logfile0" "%TARGET_ROOT%\mysql\data\ib_logfile0"
call :copy_file "%XAMPP_ROOT%\mysql\data\ib_logfile1" "%TARGET_ROOT%\mysql\data\ib_logfile1"
call :copy_file "%XAMPP_ROOT%\mysql\data\aria_log.00000001" "%TARGET_ROOT%\mysql\data\aria_log.00000001"
call :copy_file "%XAMPP_ROOT%\mysql\data\aria_log_control" "%TARGET_ROOT%\mysql\data\aria_log_control"
call :copy_file "%XAMPP_ROOT%\mysql\data\my.ini" "%TARGET_ROOT%\mysql\data\my.ini"
call :copy_file "%XAMPP_ROOT%\mysql\data\multi-master.info" "%TARGET_ROOT%\mysql\data\multi-master.info"

if exist "%XAMPP_ROOT%\mysql\data\mysql" call :copy_dir "%XAMPP_ROOT%\mysql\data\mysql" "%TARGET_ROOT%\mysql\data\mysql"
if exist "%XAMPP_ROOT%\mysql\data\performance_schema" call :copy_dir "%XAMPP_ROOT%\mysql\data\performance_schema" "%TARGET_ROOT%\mysql\data\performance_schema"
if exist "%XAMPP_ROOT%\mysql\data\phpmyadmin" call :copy_dir "%XAMPP_ROOT%\mysql\data\phpmyadmin" "%TARGET_ROOT%\mysql\data\phpmyadmin"
if exist "%XAMPP_ROOT%\mysql\data\test" call :copy_dir "%XAMPP_ROOT%\mysql\data\test" "%TARGET_ROOT%\mysql\data\test"
if exist "%XAMPP_ROOT%\mysql\data\repair-backups" call :copy_dir "%XAMPP_ROOT%\mysql\data\repair-backups" "%TARGET_ROOT%\mysql\data\repair-backups"

echo Desktop runtime staged at:
echo %TARGET_ROOT%
exit /b 0

:copy_dir
if not exist "%~1" exit /b 0
robocopy "%~1" "%~2" /MIR >nul
set "ROBOCODE=%ERRORLEVEL%"
if %ROBOCODE% GEQ 8 exit /b %ROBOCODE%
exit /b 0

:copy_file
if not exist "%~1" exit /b 0
copy /Y "%~1" "%~2" >nul
exit /b 0