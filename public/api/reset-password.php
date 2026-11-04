<?php
/**
 * Password reset endpoint
 * POST /api/reset-password
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



// Handle POST /api/reset-password
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $token = $input['token'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';

    if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
        Response::badRequest('All fields are required');
    }

    if (!Validator::passwordMatch($newPassword, $confirmPassword)) {
        Response::badRequest('Passwords do not match');
    }

    if (strlen($newPassword) < 8) {
        Response::badRequest('Password must be at least 8 characters');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $tokenHash = Token::hash($token);

        $stmt = $db->prepare('SELECT * FROM password_reset_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP()');
        $stmt->execute([$tokenHash]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            Response::badRequest('Invalid or expired reset token');
        }

        // Hash new password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Update user password
        $updateStmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $updateStmt->execute([$passwordHash, $tokenData['user_id']]);

        // Delete the reset token
        $deleteStmt = $db->prepare('DELETE FROM password_reset_tokens WHERE id = ?');
        $deleteStmt->execute([$tokenData['id']]);

        Response::json(['message' => 'Password reset successfully']);

    } catch (Exception $e) {
        error_log('Password reset error: ' . $e->getMessage());
        Response::serverError('Password reset failed');
    }
}

Response::notFound('Endpoint not found');
