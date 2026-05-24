<?php

declare(strict_types=1);

function administration_app_root(): string
{
    return dirname(__DIR__);
}

function administration_data_root(): string
{
    $configuredPath = administration_discover_data_root();

    return $configuredPath === '' ? administration_app_root() : $configuredPath;
}

function administration_runtime_env_path(): string
{
    return administration_data_root() . DIRECTORY_SEPARATOR . '.env.runtime';
}

function administration_runtime_config_exists(): bool
{
    return is_file(administration_runtime_env_path());
}

function administration_setup_is_available(): bool
{
    return !administration_runtime_config_exists() || administration_env_bool('APP_ALLOW_SETUP', false);
}

function administration_put_env(string $name, string $value): void
{
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv($name . '=' . $value);
}

function administration_parse_env_file(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return [];
    }

    $values = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        $separatorPosition = strpos($line, '=');
        if ($separatorPosition === false) {
            continue;
        }

        $name = trim(substr($line, 0, $separatorPosition));
        $value = trim(substr($line, $separatorPosition + 1));

        if ($name === '') {
            continue;
        }

        $length = strlen($value);
        if ($length >= 2) {
            $first = $value[0];
            $last = $value[$length - 1];

            if (($first === '"' && $last === '"') || ($first === '\'' && $last === '\'')) {
                $value = substr($value, 1, -1);
            }
        }

        $values[$name] = $value;
    }

    return $values;
}

function administration_raw_env_value(string $name): ?string
{
    if (array_key_exists($name, $_ENV) && $_ENV[$name] !== '') {
        return (string) $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== '') {
        return (string) $_SERVER[$name];
    }

    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    return null;
}

function administration_discover_data_root(): string
{
    static $resolvedPath = null;

    if ($resolvedPath !== null) {
        return $resolvedPath;
    }

    $configuredPath = administration_raw_env_value('ADMINISTRATION_DATA_DIR');
    if ($configuredPath === null || trim($configuredPath) === '') {
        $appRoot = administration_app_root();

        foreach (['.env', '.env.local'] as $envFileName) {
            $values = administration_parse_env_file($appRoot . DIRECTORY_SEPARATOR . $envFileName);
            if (array_key_exists('ADMINISTRATION_DATA_DIR', $values) && trim((string) $values['ADMINISTRATION_DATA_DIR']) !== '') {
                $configuredPath = (string) $values['ADMINISTRATION_DATA_DIR'];
                break;
            }
        }
    }

    if ($configuredPath === null || trim($configuredPath) === '') {
        $resolvedPath = administration_app_root();

        return $resolvedPath;
    }

    $normalizedPath = rtrim(trim($configuredPath), "\\/");
    $resolvedPath = $normalizedPath === '' ? administration_app_root() : $normalizedPath;

    return $resolvedPath;
}

function administration_load_env_file(string $path): void
{
    foreach (administration_parse_env_file($path) as $name => $value) {
        // Do not override values already set by the system environment.
        // System env vars (e.g. from Render) take priority over .env file defaults.
        // This includes empty-string values, which explicitly unset a default.
        if (getenv($name) !== false) {
            continue;
        }

        administration_put_env($name, $value);
    }
}

function administration_load_environment(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    $appRoot = administration_app_root();
    $envPaths = [
        $appRoot . DIRECTORY_SEPARATOR . '.env',
        $appRoot . DIRECTORY_SEPARATOR . '.env.local',
        administration_runtime_env_path(),
    ];

    foreach ($envPaths as $envPath) {
        administration_load_env_file($envPath);
    }

    $loaded = true;
}

function administration_env(string $name, ?string $default = null): ?string
{
    administration_load_environment();

    if (array_key_exists($name, $_ENV) && $_ENV[$name] !== '') {
        return (string) $_ENV[$name];
    }

    if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== '') {
        return (string) $_SERVER[$name];
    }

    $value = getenv($name);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    return $default;
}

function administration_env_int(string $name, int $default): int
{
    $value = administration_env($name, null);
    if ($value === null || $value === '' || !is_numeric($value)) {
        return $default;
    }

    return (int) $value;
}

function administration_env_bool(string $name, bool $default): bool
{
    $value = administration_env($name, null);
    if ($value === null || $value === '') {
        return $default;
    }

    $normalized = strtolower(trim($value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return $default;
}

function administration_path_has_xampp_apache(string $path): bool
{
    $normalizedPath = rtrim(trim($path), "\\/");
    if ($normalizedPath === '') {
        return false;
    }

    $httpdPath = $normalizedPath . DIRECTORY_SEPARATOR . 'apache' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'httpd.exe';

    return is_file($httpdPath);
}

function administration_detect_xampp_root(): string
{
    $candidates = [];

    $systemDrive = getenv('SystemDrive');
    if (is_string($systemDrive) && trim($systemDrive) !== '') {
        $candidates[] = rtrim($systemDrive, "\\/") . DIRECTORY_SEPARATOR . 'xampp';
    }

    $candidates[] = 'C:\xampp';

    foreach (['ProgramFiles', 'ProgramFiles(x86)'] as $environmentName) {
        $basePath = getenv($environmentName);
        if (is_string($basePath) && trim($basePath) !== '') {
            $candidates[] = rtrim($basePath, "\\/") . DIRECTORY_SEPARATOR . 'xampp';
        }
    }

    foreach (range('D', 'Z') as $driveLetter) {
        $candidates[] = $driveLetter . ':\\xampp';
    }

    foreach (array_values(array_unique($candidates)) as $candidate) {
        if (administration_path_has_xampp_apache($candidate)) {
            return $candidate;
        }
    }

    return 'C:\xampp';
}

function administration_default_xampp_root(): string
{
    $configuredPath = trim((string) administration_env('XAMPP_ROOT', ''));
    if ($configuredPath !== '' && administration_path_has_xampp_apache($configuredPath)) {
        return $configuredPath;
    }

    return administration_detect_xampp_root();
}

function administration_default_app_folder(): string
{
    $configuredValue = administration_env('APP_FOLDER', null);

    // If APP_FOLDER is explicitly set (even to empty string), use it as-is.
    // An empty value means the app is deployed at the root (e.g. on Render).
    if ($configuredValue !== null) {
        return trim($configuredValue);
    }

    // Default fallback for local XAMPP development.
    return 'administration';
}

function administration_default_app_entry_file(): string
{
    $configuredValue = trim((string) administration_env('APP_ENTRY_FILE', 'index.php'));

    return $configuredValue === '' ? 'index.php' : $configuredValue;
}

function administration_default_apache_sync_target(): string
{
    $configuredPath = trim((string) administration_env('APACHE_SYNC_TARGET', ''));
    if ($configuredPath !== '') {
        return $configuredPath;
    }

    return administration_default_xampp_root()
        . DIRECTORY_SEPARATOR
        . 'htdocs'
        . DIRECTORY_SEPARATOR
        . administration_default_app_folder();
}

function administration_server_mode(): string
{
    $mode = strtolower(trim((string) administration_env('APP_SERVER_MODE', 'desktop')));

    return in_array($mode, ['desktop', 'school-server'], true) ? $mode : 'desktop';
}

function administration_server_port(): int
{
    $port = administration_env_int('APP_SERVER_PORT', 80);

    return ($port >= 1 && $port <= 65535) ? $port : 80;
}

function administration_server_open_firewall(): bool
{
    $default = administration_server_mode() === 'school-server';

    return administration_env_bool('APP_SERVER_OPEN_FIREWALL', $default);
}

function administration_detect_local_ipv4_addresses(): array
{
    $addresses = [];

    $serverAddress = trim((string) ($_SERVER['SERVER_ADDR'] ?? ''));
    if ($serverAddress !== '') {
        $addresses[] = $serverAddress;
    }

    $hostname = gethostname();
    if (is_string($hostname) && trim($hostname) !== '') {
        $resolvedAddresses = @gethostbynamel($hostname);
        if (is_array($resolvedAddresses)) {
            $addresses = array_merge($addresses, $resolvedAddresses);
        }
    }

    $filteredAddresses = [];
    foreach ($addresses as $address) {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            continue;
        }

        if (str_starts_with($address, '127.') || str_starts_with($address, '169.254.') || $address === '0.0.0.0') {
            continue;
        }

        $filteredAddresses[$address] = $address;
    }

    return array_values($filteredAddresses);
}

function administration_configured_server_public_host(): string
{
    return trim((string) administration_env('APP_SERVER_PUBLIC_HOST', ''));
}

function administration_is_loopback_host(string $host): bool
{
    return in_array(strtolower(trim($host)), ['localhost', '127.0.0.1', '::1'], true);
}

function administration_server_public_host(): string
{
    $configuredHost = administration_configured_server_public_host();

    if ($configuredHost !== '' && (administration_server_mode() !== 'school-server' || !administration_is_loopback_host($configuredHost))) {
        return $configuredHost;
    }

    $detectedAddresses = administration_detect_local_ipv4_addresses();
    if ($detectedAddresses !== []) {
        return $detectedAddresses[0];
    }

    if ($configuredHost !== '') {
        return $configuredHost;
    }

    return 'localhost';
}

function administration_application_base_path(): string
{
    $folder = trim(administration_default_app_folder(), "\\/");

    return '/' . ($folder === '' ? '' : $folder . '/') ;
}

function administration_format_application_url(string $host, ?string $entryFile = null): string
{
    $normalizedHost = trim($host);
    if ($normalizedHost === '') {
        $normalizedHost = 'localhost';
    }

    $port = administration_server_port();
    $portFragment = $port === 80 ? '' : ':' . $port;
    $path = administration_application_base_path() . ltrim((string) ($entryFile ?? administration_default_app_entry_file()), '/');

    return 'http://' . $normalizedHost . $portFragment . $path;
}

function administration_local_application_url(?string $entryFile = null): string
{
    return administration_format_application_url('localhost', $entryFile);
}

function administration_client_application_url(?string $entryFile = null): string
{
    return administration_format_application_url(administration_server_public_host(), $entryFile);
}

function administration_runtime_env_values(): array
{
    return administration_parse_env_file(administration_runtime_env_path());
}

function administration_update_runtime_env(array $updatedValues): void
{
    $values = administration_runtime_env_values();

    foreach ($updatedValues as $name => $value) {
        $values[(string) $name] = (string) $value;
        administration_put_env((string) $name, (string) $value);
    }

    administration_write_env_file($values);
}

function administration_write_env_file(array $values, ?string $path = null): void
{
    $targetPath = $path ?? administration_runtime_env_path();
    $targetDirectory = dirname($targetPath);
    $lines = [];

    if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0777, true) && !is_dir($targetDirectory)) {
        throw new RuntimeException('The runtime configuration directory could not be created.');
    }

    foreach ($values as $name => $value) {
        $name = trim((string) $name);
        if ($name === '') {
            continue;
        }

        $normalizedValue = str_replace(["\r", "\n"], [' ', ' '], (string) $value);
        if (preg_match('/\s|#|;|"/', $normalizedValue) === 1) {
            $normalizedValue = '"' . addcslashes($normalizedValue, "\\\"") . '"';
        }

        $lines[] = $name . '=' . $normalizedValue;
    }

    $contents = implode(PHP_EOL, $lines) . PHP_EOL;
    if (file_put_contents($targetPath, $contents, LOCK_EX) === false) {
        throw new RuntimeException('The runtime environment file could not be written.');
    }
}
