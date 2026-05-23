@echo off

if not defined APP_FOLDER set "APP_FOLDER=administration"
if not defined APP_ENTRY_FILE set "APP_ENTRY_FILE=index.php"

set "ORIGINAL_XAMPP_ROOT=%XAMPP_ROOT%"

if defined ADMINISTRATION_RUNTIME_ROOT if exist "%ADMINISTRATION_RUNTIME_ROOT%\apache\bin\httpd.exe" goto use_runtime_root

if defined XAMPP_ROOT if exist "%XAMPP_ROOT%\apache\bin\httpd.exe" goto resolve_target

set "XAMPP_ROOT="

if defined SystemDrive call :probe_candidate "%SystemDrive%\xampp"
if defined XAMPP_ROOT goto resolve_target

call :probe_candidate "C:\xampp"
if defined XAMPP_ROOT goto resolve_target

if defined ProgramFiles call :probe_candidate "%ProgramFiles%\xampp"
if defined XAMPP_ROOT goto resolve_target

if defined ProgramFiles(x86) call :probe_candidate "%ProgramFiles(x86)%\xampp"
if defined XAMPP_ROOT goto resolve_target

for %%D in (D E F G H I J K L M N O P Q R S T U V W X Y Z) do (
  if not defined XAMPP_ROOT call :probe_candidate "%%D:\xampp"
)

:resolve_target
if not defined XAMPP_ROOT exit /b 0

if not defined APACHE_SYNC_TARGET goto set_target_from_root

if defined ADMINISTRATION_RUNTIME_ROOT if /I "%XAMPP_ROOT%"=="%ADMINISTRATION_RUNTIME_ROOT%" goto set_target_from_root

if defined ORIGINAL_XAMPP_ROOT if /I "%APACHE_SYNC_TARGET%"=="%ORIGINAL_XAMPP_ROOT%\htdocs\%APP_FOLDER%" goto set_target_from_root

exit /b 0

:use_runtime_root
set "XAMPP_ROOT=%ADMINISTRATION_RUNTIME_ROOT%"
goto resolve_target

:set_target_from_root
set "APACHE_SYNC_TARGET=%XAMPP_ROOT%\htdocs\%APP_FOLDER%"
exit /b 0

:probe_candidate
if "%~1"=="" exit /b 0
if exist "%~1\apache\bin\httpd.exe" set "XAMPP_ROOT=%~1"
exit /b 0