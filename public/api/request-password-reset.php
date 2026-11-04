<?php
/**
 * Request password reset endpoint
 * POST /api/request-password-reset
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Validator.php';
require_once __DIR__ . '/../../src/RateLimiter.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Token.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];

// Rate limiting (10 attempts per 15 minutes)
$ip = RateLimiter::getClientIP();
if (!RateLimiter::check($ip, 'password-reset', 10, 900)) {
    Response::error('Too many password reset attempts. Please try again later.', 429);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $email = Validator::sanitizeEmail($input['email'] ?? '');

    if (empty($email)) {
        Response::badRequest('Email required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Don't reveal if email exists or not
        if (!$user) {
            Response::json(['message' => 'If an account exists with this email, a password reset link will be sent.']);
        }

        // Delete existing tokens
        $deleteStmt = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
        $deleteStmt->execute([$user['id']]);

        // Generate reset token
        $token = Token::generate();
        $tokenHash = Token::hash($token);
        $expiresAt = Token::getPasswordResetExpiry();

        $tokenStmt = $db->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $tokenStmt->execute([$user['id'], $tokenHash, $expiresAt]);

        // Send password reset email
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/reset-password';
        $emailSent = Mailer::sendPasswordReset($email, $user['full_name'] ?? $email, $token, $baseUrl);

        if (!$emailSent && DEV_MODE) {
            error_log("DEV MODE: Password Reset Link: $baseUrl?token=$token");
        }

        Response::json(['message' => 'If an account exists with this email, a password reset link will be sent.']);

    } catch (Exception $e) {
        error_log('Password reset request error: ' . $e->getMessage());
        Response::serverError('Failed to send password reset email');
    }
}

Response::notFound('Endpoint not found');
