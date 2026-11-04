<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

try {
    session_start();
} catch (Exception $e) {
    // Session might already be started
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if (!$email || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        // Users table might not exist
        echo json_encode(['error' => 'User authentication system not available']);
        exit;
    }

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
        exit;
    }

    // Check if email is verified
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode(['error' => 'Please verify your email before logging in']);
        exit;
    }

    // Create session
    unset($user['password_hash']);
    $_SESSION['user'] = $user;

    echo json_encode([
        'message' => 'Login successful',
        'user' => $user
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Login API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Login failed', 'message' => $e->getMessage()]);
}
?>
