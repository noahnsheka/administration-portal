<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

if (!administration_runtime_config_exists()) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Run setup before signing in.']);
    exit;
}

$payload = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$accountNumber = trim((string) ($payload['accountNumber'] ?? ''));
$pin = trim((string) ($payload['pin'] ?? ''));
$role = strtolower(trim((string) ($payload['role'] ?? '')));

$allowedRoles = ['student', 'staff', 'administrator'];
if ($accountNumber === '' || $pin === '' || !in_array($role, $allowedRoles, true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please provide account number, PIN, and role.']);
    exit;
}

$dbRole = $role === 'administrator' ? 'admin' : $role;

try {
    $pdo = getDatabaseConnection();
    $stmt = $pdo->prepare(
        'SELECT id, account_number, full_name, role FROM users WHERE account_number = :account_number AND pin_code = :pin_code AND role = :role AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([
        'account_number' => $accountNumber,
        'pin_code' => $pin,
        'role' => $dbRole,
    ]);

    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid login credentials.']);
        exit;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'account_number' => $user['account_number'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];

    $redirectMap = [
        'student' => 'student/dashboard.php',
        'staff' => 'staff/dashboard.php',
        'admin' => 'admin/dashboard.php',
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'redirect' => $redirectMap[$user['role']] ?? 'index.php',
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}
