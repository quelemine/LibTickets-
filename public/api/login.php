<?php
/**
 * Login endpoint
 * POST /api/login
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Validator.php';
require_once __DIR__ . '/../../src/RateLimiter.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Token.php';

// Start session
Auth::startSession();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    Response::badRequest('Invalid JSON');
}

$email = Validator::sanitizeEmail($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    Response::badRequest('Email and password required');
}

if (!Validator::email($email)) {
    Response::badRequest('Invalid email format');
}

try {
    $db = Db::getInstance()->getConnection();

    // Get user by email
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        Response::unauthorized('Invalid credentials');
    }

    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Rate limiting (10 failed attempts per 15 minutes)
        $ip = RateLimiter::getClientIP();
        if (!RateLimiter::check($ip, 'login', 10, 900)) {
            Response::error('Too many login attempts. Please try again later.', 429);
        }
        Response::unauthorized('Invalid credentials');
    }

    // Check if password needs rehash
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateStmt->execute([$newHash, $user['id']]);
    }

    // Check if 2FA is enabled
    if ($user['two_factor_enabled'] == 1) {
        // Generate 2FA code
        $code = Token::generate2FACode();
        $expiresAt = Token::get2FAExpiry();

        // Store code
        $updateStmt = $db->prepare('UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?');
        $updateStmt->execute([$code, $expiresAt, $user['id']]);

        // Send email
        $emailSent = Mailer::send2FACode($user['email'], $code, 'login');

        if (!$emailSent && DEV_MODE) {
            error_log("DEV MODE: 2FA Code for {$user['email']}: $code");
        }

        // Set pending user
        Auth::setPendingUser($user);

        Response::json([
            'message' => '2FA code sent to your email',
            'requiresTwoFactor' => true,
            'email' => $user['email']
        ]);
    }

    // No 2FA, complete login
    Auth::login($user);

    Response::json([
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'full_name' => $user['full_name'],
            'phone' => $user['phone'],
            'is_verified' => (int)$user['is_verified']
        ]
    ]);

} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    Response::serverError('Login failed');
}
