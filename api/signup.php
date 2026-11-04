<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    session_start();
} catch (Exception $e) {
    // Session might already be started
}

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password are required']);
        exit;
    }

    try {
        // Check if user already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        // Hash password
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        // Create user
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, full_name, phone, role, is_verified) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['email'],
            $passwordHash,
            $data['full_name'] ?? '',
            $data['phone'] ?? '',
            'customer',
            1 // Auto-verify for now
        ]);

        $userId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $userId,
                'email' => $data['email'],
                'full_name' => $data['full_name'] ?? '',
                'role' => 'customer'
            ],
            'message' => 'Account created successfully'
        ]);
    } catch (Exception $e) {
        // Users table might not exist
        echo json_encode(['error' => 'User registration system not available']);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Signup API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Registration failed', 'message' => $e->getMessage()]);
}
?>
