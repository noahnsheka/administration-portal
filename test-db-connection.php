<?php
declare(strict_types=1);

require 'config/env.php';
require 'config/database.php';

try {
    $pdo = getDatabaseConnection();
    echo "✓ Database connection successful!\n";
    echo "Connected to: " . administration_env('DB_HOST') . "\n";
    echo "Database: " . administration_env('DB_NAME') . "\n";
    echo "User: " . administration_env('DB_USER') . "\n";
} catch (Exception $e) {
    echo "✗ Connection failed:\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
