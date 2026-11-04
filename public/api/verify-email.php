<?php
/**
 * Email verification endpoints
 * GET /api/verify-email
 * POST /api/resend-verification
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Validator.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Token.php';
require_once __DIR__ . '/../../src/RateLimiter.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Strip base path if present
$basePath = '/libtickets';
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Remove query string
$path = strtok($path, '?');

// Handle GET /api/verify-email
if ($method === 'GET' && strpos($path, 'verify-email') !== false && strpos($path, 'resend') === false) {
    $token = $_GET['token'] ?? '';

    if (empty($token)) {
        Response::badRequest('Verification token required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $tokenHash = Token::hash($token);

        $stmt = $db->prepare('SELECT * FROM email_verification_tokens WHERE token = ? AND expires_at > UTC_TIMESTAMP()');
        $stmt->execute([$tokenHash]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            Response::badRequest('Invalid or expired verification token');
        }

        // Update user as verified
        $updateStmt = $db->prepare('UPDATE users SET is_verified = 1 WHERE id = ?');
        $updateStmt->execute([$tokenData['user_id']]);

        // Delete the verification token
        $deleteStmt = $db->prepare('DELETE FROM email_verification_tokens WHERE id = ?');
        $deleteStmt->execute([$tokenData['id']]);

        Response::json(['message' => 'Email verified successfully']);

    } catch (Exception $e) {
        error_log('Email verification error: ' . $e->getMessage());
        Response::serverError('Verification failed');
    }
}

// Handle POST /api/resend-verification
if ($method === 'POST' && strpos($path, 'resend-verification') !== false) {
    // Rate limiting (10 attempts per 15 minutes)
    $ip = RateLimiter::getClientIP();
    if (!RateLimiter::check($ip, 'resend-verification', 10, 900)) {
        Response::error('Too many resend attempts. Please try again later.', 429);
    }

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

        if (!$user) {
            Response::notFound('User not found');
        }

        if ($user['is_verified'] == 1) {
            Response::badRequest('Email already verified');
        }

        // Delete existing tokens
        $deleteStmt = $db->prepare('DELETE FROM email_verification_tokens WHERE user_id = ?');
        $deleteStmt->execute([$user['id']]);

        // Generate new verification token
        $token = Token::generate();
        $tokenHash = Token::hash($token);
        $expiresAt = Token::getEmailVerificationExpiry();

        $tokenStmt = $db->prepare('INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $tokenStmt->execute([$user['id'], $tokenHash, $expiresAt]);

        // Send verification email
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/verify-email';
        $emailSent = Mailer::sendEmailVerification($email, $user['full_name'] ?? $email, $token, $baseUrl);

        if (!$emailSent && DEV_MODE) {
            error_log("DEV MODE: Email Verification Link: $baseUrl?token=$token");
        }

        Response::json(['message' => 'Verification email sent successfully']);

    } catch (Exception $e) {
        error_log('Resend verification error: ' . $e->getMessage());
        Response::serverError('Failed to send verification email');
    }
}

Response::notFound('Endpoint not found');
