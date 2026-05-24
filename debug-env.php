<?php
declare(strict_types=1);

require 'config/env.php';

// Load environment
administration_load_environment();

echo "=== Environment Variable Debug ===\n\n";

// Check getenv (system level)
echo "1. System Environment Variables (getenv):\n";
echo "   DB_HOST: " . (getenv('DB_HOST') ?: '(not set)') . "\n";
echo "   DB_PORT: " . (getenv('DB_PORT') ?: '(not set)') . "\n";
echo "   DB_NAME: " . (getenv('DB_NAME') ?: '(not set)') . "\n";
echo "   DB_USER: " . (getenv('DB_USER') ?: '(not set)') . "\n";
echo "   DB_PASS: " . (getenv('DB_PASS') ? '(SET - ' . strlen(getenv('DB_PASS')) . ' chars)' : '(not set)') . "\n";
echo "   DB_PASSWORD: " . (getenv('DB_PASSWORD') ? '(SET - ' . strlen(getenv('DB_PASSWORD')) . ' chars)' : '(not set)') . "\n\n";

// Check administration_env 
echo "2. Via administration_env():\n";
echo "   DB_HOST: " . administration_env('DB_HOST', 'NOT_FOUND') . "\n";
echo "   DB_PORT: " . administration_env('DB_PORT', 'NOT_FOUND') . "\n";
echo "   DB_NAME: " . administration_env('DB_NAME', 'NOT_FOUND') . "\n";
echo "   DB_USER: " . administration_env('DB_USER', 'NOT_FOUND') . "\n";
echo "   DB_PASS: " . (administration_env('DB_PASS') ? '(SET - ' . strlen(administration_env('DB_PASS')) . ' chars)' : '(not set)') . "\n";
echo "   DB_PASSWORD: " . (administration_env('DB_PASSWORD') ? '(SET - ' . strlen(administration_env('DB_PASSWORD')) . ' chars)' : '(not set)') . "\n\n";

// Check $_ENV and $_SERVER
echo "3. In \$_ENV:\n";
echo "   DB_PASS exists: " . (isset($_ENV['DB_PASS']) ? 'YES' : 'NO') . "\n";
echo "   DB_PASSWORD exists: " . (isset($_ENV['DB_PASSWORD']) ? 'YES' : 'NO') . "\n\n";

echo "4. In \$_SERVER:\n";
echo "   DB_PASS exists: " . (isset($_SERVER['DB_PASS']) ? 'YES' : 'NO') . "\n";
echo "   DB_PASSWORD exists: " . (isset($_SERVER['DB_PASSWORD']) ? 'YES' : 'NO') . "\n\n";

// Show what would be used for connection
echo "5. What getDatabaseConnection() will use:\n";
$host = getenv('DB_HOST') ?: administration_env('DB_HOST', 'localhost');
$port = getenv('DB_PORT') ?: administration_env('DB_PORT', '5432');
$dbName = getenv('DB_NAME') ?: administration_env('DB_NAME', 'administration_suite');
$username = getenv('DB_USER') ?: administration_env('DB_USER', 'postgres');
$password = getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: administration_env('DB_PASS', administration_env('DB_PASSWORD', '')));

echo "   Host: $host\n";
echo "   Port: $port\n";
echo "   DB: $dbName\n";
echo "   User: $username\n";
echo "   Pass: " . ($password ? '(SET - ' . strlen($password) . ' chars)' : '(EMPTY!)') . "\n";
echo "   Pass value: " . ($password ? $password : '(EMPTY!)') . "\n";

// Check .env.runtime
echo "\n6. .env.runtime path: " . administration_runtime_env_path() . "\n";
echo "   Exists: " . (administration_runtime_config_exists() ? 'YES' : 'NO') . "\n";
