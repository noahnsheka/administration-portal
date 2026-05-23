param(
    [string]$RuntimeRoot
)

if ([string]::IsNullOrWhiteSpace($RuntimeRoot)) {
    $RuntimeRoot = $env:ADMINISTRATION_RUNTIME_ROOT
}

if ([string]::IsNullOrWhiteSpace($RuntimeRoot)) {
    $RuntimeRoot = $env:XAMPP_ROOT
}

if ([string]::IsNullOrWhiteSpace($RuntimeRoot)) {
    exit 0
}

$root = [System.IO.Path]::GetFullPath($RuntimeRoot)

if (-not (Test-Path -LiteralPath (Join-Path $root 'apache\bin\httpd.exe'))) {
    exit 0
}

if (-not (Test-Path -LiteralPath (Join-Path $root 'mysql\bin\mysqld.exe'))) {
    exit 0
}

$rootFwd = $root -replace '\\', '/'
$apacheFwd = $rootFwd + '/apache'
$apacheBinFwd = $apacheFwd + '/bin'
$apacheLogsFwd = $apacheFwd + '/logs'
$htdocsFwd = $rootFwd + '/htdocs'
$cgiFwd = $rootFwd + '/cgi-bin'
$phpFwd = $rootFwd + '/php'
$phpLogsFwd = $phpFwd + '/logs'
$mysqlFwd = $rootFwd + '/mysql'
$tmpFwd = $rootFwd + '/tmp'
$licensesFwd = $rootFwd + '/licenses'
$phpMyAdminFwd = $rootFwd + '/phpMyAdmin'
$webalizerFwd = $rootFwd + '/webalizer'

$apacheWin = Join-Path $root 'apache'
$apacheBinWin = Join-Path $apacheWin 'bin'
$apacheLogsWin = Join-Path $apacheWin 'logs'
$mysqlWin = Join-Path $root 'mysql'
$mysqlBinWin = Join-Path $mysqlWin 'bin'
$mysqlDataWin = Join-Path $mysqlWin 'data'
$phpWin = Join-Path $root 'php'
$phpExtWin = Join-Path $phpWin 'ext'
$apacheConfWin = Join-Path $root 'apache\conf'

function Update-File {
    param(
        [string]$Path,
        [scriptblock]$Updater
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        return
    }

    $content = Get-Content -LiteralPath $Path -Raw
    $updated = & $Updater $content

    if ($updated -ne $content) {
        [System.IO.File]::WriteAllText($Path, $updated, [System.Text.UTF8Encoding]::new($false))
    }
}

Update-File (Join-Path $root 'apache\conf\httpd.conf') {
    param([string]$content)

    $content = $content -replace '(?m)^Define SRVROOT.*$', ("Define SRVROOT `"{0}`"" -f $apacheFwd)
    $content = $content -replace '(?m)^ServerRoot.*$', ("ServerRoot `"{0}`"" -f $apacheFwd)
    $content = $content -replace '(?m)^DocumentRoot.*$', ("DocumentRoot `"{0}`"" -f $htdocsFwd)
    $content = $content -replace '(?m)^\s*ScriptAlias /cgi-bin/.*$', ("    ScriptAlias /cgi-bin/ `"{0}/`"" -f $cgiFwd)

    $lines = $content -split "`r?`n"
    for ($index = 0; $index -lt $lines.Length; $index++) {
        if ($lines[$index] -match '^\s*<Directory\b.*?/htdocs"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$htdocsFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/cgi-bin"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$cgiFwd`">"
        }
    }

    $content = [string]::Join([Environment]::NewLine, $lines)

    return $content
}

Update-File (Join-Path $root 'apache\conf\extra\httpd-xampp.conf') {
    param([string]$content)

    $content = $content -replace '(?m)^\s*SetEnv MIBDIRS.*$', ("    SetEnv MIBDIRS `"{0}/extras/mibs`"" -f $phpFwd)
    $content = $content -replace '(?m)^\s*SetEnv MYSQL_HOME.*$', ("    SetEnv MYSQL_HOME `"{0}/bin`"" -f $mysqlFwd)
    $content = $content -replace '(?m)^\s*SetEnv OPENSSL_CONF.*$', ("    SetEnv OPENSSL_CONF `"{0}/bin/openssl.cnf`"" -f $apacheFwd)
    $content = $content -replace '(?m)^\s*SetEnv PHP_PEAR_SYSCONF_DIR.*$', ("    SetEnv PHP_PEAR_SYSCONF_DIR `"{0}`"" -f $phpFwd)
    $content = $content -replace '(?m)^\s*SetEnv PHPRC.*$', ("    SetEnv PHPRC `"{0}`"" -f $phpFwd)
    $content = $content -replace '(?m)^\s*SetEnv TMP.*$', ("    SetEnv TMP `"{0}`"" -f $tmpFwd)
    $content = $content -replace '(?m)^LoadFile .*php8ts\.dll.*$', ("LoadFile `"{0}/php8ts.dll`"" -f $phpFwd)
    $content = $content -replace '(?m)^LoadFile .*libpq\.dll.*$', ("LoadFile `"{0}/libpq.dll`"" -f $phpFwd)
    $content = $content -replace '(?m)^LoadFile .*libsqlite3\.dll.*$', ("LoadFile `"{0}/libsqlite3.dll`"" -f $phpFwd)
    $content = $content -replace '(?m)^LoadModule php_module .*php8apache2_4\.dll.*$', ("LoadModule php_module `"{0}/php8apache2_4.dll`"" -f $phpFwd)
    $content = $content -replace '(?m)^\s*PHPINIDir.*$', ("    PHPINIDir `"{0}`"" -f $phpFwd)
    $content = $content -replace '(?m)^ScriptAlias /php-cgi/.*$', ("ScriptAlias /php-cgi/ `"{0}/`"" -f $phpFwd)
    $content = $content -replace '(?m)^\s*Alias /licenses.*$', ("    Alias /licenses `"{0}/`"" -f $licensesFwd)
    $content = $content -replace '(?m)^\s*Alias /phpmyadmin.*$', ("    Alias /phpmyadmin `"{0}/`"" -f $phpMyAdminFwd)
    $content = $content -replace '(?m)^\s*Alias /webalizer.*$', ("    Alias /webalizer `"{0}/`"" -f $webalizerFwd)

    $lines = $content -split "`r?`n"
    for ($index = 0; $index -lt $lines.Length; $index++) {
        if ($lines[$index] -match '^\s*<Directory\b.*?/php"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$phpFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/cgi-bin"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$cgiFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/htdocs/xampp"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$htdocsFwd/xampp`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/licenses"?\s*>\s*$') {
            $lines[$index] = "    <Directory `"$licensesFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/phpMyAdmin"?\s*>\s*$') {
            $lines[$index] = "    <Directory `"$phpMyAdminFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/webalizer"?\s*>\s*$') {
            $lines[$index] = "    <Directory `"$webalizerFwd`">"
        }
    }

    $content = [string]::Join([Environment]::NewLine, $lines)

    return $content
}

Update-File (Join-Path $root 'apache\conf\extra\httpd-ssl.conf') {
    param([string]$content)

    $content = $content -replace '(?m)^SSLSessionCache\s+.*$', ("SSLSessionCache `"shmcb:{0}/ssl_scache(512000)`"" -f $apacheLogsFwd)
    $content = $content -replace '(?m)^DocumentRoot.*$', ("DocumentRoot `"{0}`"" -f $htdocsFwd)
    $content = $content -replace '(?m)^ErrorLog\s+.*$', ("ErrorLog `"{0}/error.log`"" -f $apacheLogsFwd)
    $content = $content -replace '(?m)^TransferLog\s+.*$', ("TransferLog `"{0}/access.log`"" -f $apacheLogsFwd)

    $lines = $content -split "`r?`n"
    for ($index = 0; $index -lt $lines.Length; $index++) {
        if ($lines[$index] -match '^CustomLog\s+.*ssl_request\.log.*$') {
            $lines[$index] = ('CustomLog "{0}/ssl_request.log" \' -f $apacheLogsFwd)
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/htdocs"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$htdocsFwd`">"
            continue
        }

        if ($lines[$index] -match '^\s*<Directory\b.*?/apache/cgi-bin"?\s*>\s*$') {
            $lines[$index] = "<Directory `"$cgiFwd`">"
        }
    }

    $content = [string]::Join([Environment]::NewLine, $lines)

    return $content
}

Update-File (Join-Path $root 'apache\scripts\ctl.bat') {
    param([string]$content)

    $lines = $content -split "`r?`n"
    for ($index = 0; $index -lt $lines.Length; $index++) {
        if ($lines[$index] -match 'httpd\.exe' -and $lines[$index] -match '^cmd\.exe /C start /B /MIN') {
            $lines[$index] = ('cmd.exe /C start /B /MIN "" "{0}"' -f (Join-Path $apacheBinWin 'httpd.exe'))
            continue
        }

        if ($lines[$index] -match 'killprocess\.bat' -and $lines[$index] -match 'httpd\.exe') {
            $lines[$index] = ('cmd.exe /C start "" /MIN call "{0}" "httpd.exe"' -f (Join-Path $root 'killprocess.bat'))
            continue
        }

        if ($lines[$index] -match 'httpd\.pid' -and $lines[$index] -match '^if not exist') {
            $lines[$index] = ('if not exist "{0}" GOTO finish' -f (Join-Path $apacheLogsWin 'httpd.pid'))
            continue
        }

        if ($lines[$index] -match 'httpd\.pid' -and $lines[$index] -match '^del ') {
            $lines[$index] = ('del "{0}"' -f (Join-Path $apacheLogsWin 'httpd.pid'))
        }
    }

    $content = [string]::Join([Environment]::NewLine, $lines)

    return $content
}

Update-File (Join-Path $root 'properties.ini') {
    param([string]$content)

    $content = $content -replace '(?m)^installdir=.*$', ('installdir={0}' -f $root)
    $content = $content -replace '(?m)^apache_root_directory=.*$', ('apache_root_directory={0}' -f $apacheFwd)
    $content = $content -replace '(?m)^apache_htdocs_directory=.*$', ('apache_htdocs_directory={0}' -f $htdocsFwd)
    $content = $content -replace '(?m)^apache_configuration_directory=.*$', ('apache_configuration_directory={0}' -f $apacheConfWin)
    $content = $content -replace '(?m)^mysql_root_directory=.*$', ('mysql_root_directory={0}' -f $mysqlWin)
    $content = $content -replace '(?m)^mysql_binary_directory=.*$', ('mysql_binary_directory={0}' -f $mysqlBinWin)
    $content = $content -replace '(?m)^mysql_data_directory=.*$', ('mysql_data_directory={0}' -f $mysqlDataWin)
    $content = $content -replace '(?m)^mysql_configuration_directory=.*$', ('mysql_configuration_directory={0}' -f $mysqlBinWin)
    $content = $content -replace '(?m)^php_binary_directory=.*$', ('php_binary_directory={0}' -f $phpWin)
    $content = $content -replace '(?m)^php_configuration_directory=.*$', ('php_configuration_directory={0}' -f $phpWin)
    $content = $content -replace '(?m)^php_extensions_directory=.*$', ('php_extensions_directory={0}' -f $phpExtWin)

    return $content
}

foreach ($relativePath in @('mysql\bin\my.ini', 'mysql\data\my.ini')) {
    Update-File (Join-Path $root $relativePath) {
        param([string]$content)

        $content = $content -replace '(?m)^socket=.*$', ("socket=`"{0}/mysql.sock`"" -f $mysqlFwd)
        $content = $content -replace '(?m)^basedir=.*$', ("basedir=`"{0}`"" -f $mysqlFwd)
        $content = $content -replace '(?m)^tmpdir=.*$', ("tmpdir=`"{0}`"" -f $tmpFwd)
        $content = $content -replace '(?m)^datadir=.*$', ("datadir=`"{0}/data`"" -f $mysqlFwd)
        $content = $content -replace '(?m)^plugin_dir=.*$', ("plugin_dir=`"{0}/lib/plugin/`"" -f $mysqlFwd)
        $content = $content -replace '(?m)^innodb_data_home_dir=.*$', ("innodb_data_home_dir=`"{0}/data`"" -f $mysqlFwd)
        $content = $content -replace '(?m)^innodb_log_group_home_dir=.*$', ("innodb_log_group_home_dir=`"{0}/data`"" -f $mysqlFwd)

        return $content
    }
}

Update-File (Join-Path $root 'mysql\scripts\ctl.bat') {
    param([string]$content)

    $lines = $content -split "`r?`n"
    for ($index = 0; $index -lt $lines.Length; $index++) {
        if ($lines[$index] -match 'mysqld' -and $lines[$index] -match '--defaults-file=' ) {
            $lines[$index] = ('"{0}" --defaults-file="{1}" --standalone' -f (Join-Path $mysqlBinWin 'mysqld'), (Join-Path $mysqlBinWin 'my.ini'))
            continue
        }

        if ($lines[$index] -match 'killprocess\.bat' -and $lines[$index] -match 'mysqld\.exe') {
            $lines[$index] = ('cmd.exe /C start "" /MIN call "{0}" "mysqld.exe"' -f (Join-Path $root 'killprocess.bat'))
            continue
        }

        if ($lines[$index] -match '%computername%\.pid' -and $lines[$index] -match '^if not exist') {
            $lines[$index] = ('if not exist "{0}" goto finish' -f (Join-Path $mysqlDataWin '%computername%.pid'))
            continue
        }

        if ($lines[$index] -match '%computername%\.pid' -and $lines[$index] -match '^del ') {
            $lines[$index] = ('del "{0}"' -f (Join-Path $mysqlDataWin '%computername%.pid'))
        }
    }

    $content = [string]::Join([Environment]::NewLine, $lines)

    return $content
}

Update-File (Join-Path $root 'php\php.ini') {
    param([string]$content)

    $content = $content -replace '(?m)^include_path=.*$', ("include_path=`"{0}/PEAR`"" -f $phpFwd)
    $content = $content -replace '(?m)^extension_dir=.*$', ("extension_dir=`"{0}/ext`"" -f $phpFwd)
    $content = $content -replace '(?m)^upload_tmp_dir=.*$', ("upload_tmp_dir=`"{0}`"" -f $tmpFwd)
    $content = $content -replace '(?m)^error_log=.*$', ("error_log=`"{0}/php_error_log`"" -f $phpLogsFwd)
    $content = $content -replace '(?m)^browscap=.*$', ("browscap=`"{0}/extras/browscap.ini`"" -f $phpFwd)
    $content = $content -replace '(?m)^session\.save_path=.*$', ("session.save_path=`"{0}`"" -f $tmpFwd)
    $content = $content -replace '(?m)^curl\.cainfo=.*$', ("curl.cainfo=`"{0}/curl-ca-bundle.crt`"" -f $apacheBinFwd)
    $content = $content -replace '(?m)^openssl\.cafile=.*$', ("openssl.cafile=`"{0}/curl-ca-bundle.crt`"" -f $apacheBinFwd)

    return $content
}

foreach ($directoryPath in @(
    (Join-Path $root 'htdocs'),
    (Join-Path $root 'tmp'),
    (Join-Path $root 'apache\logs'),
    (Join-Path $root 'php\logs'),
    (Join-Path $root 'mysql\data')
)) {
    if (-not (Test-Path -LiteralPath $directoryPath)) {
        [void](New-Item -ItemType Directory -Path $directoryPath -Force)
    }
}

exit 0