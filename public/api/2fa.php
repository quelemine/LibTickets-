<?php
/**
 * 2FA endpoints
 * POST /api/verify-2fa
 * POST /api/enable-2fa
 * POST /api/confirm-2fa
 * POST /api/disable-2fa
 * GET /api/user/2fa-status
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';
require_once __DIR__ . '/../../src/Mailer.php';
require_once __DIR__ . '/../../src/Token.php';
require_once __DIR__ . '/../../src/RateLimiter.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['REQUEST_URI'];

// Handle POST /api/verify-2fa
if ($method === 'POST' && strpos($path, 'verify-2fa') !== false) {
    // Rate limiting (10 attempts per 15 minutes)
    $ip = RateLimiter::getClientIP();
    if (!RateLimiter::check($ip, 'verify-2fa', 10, 900)) {
        Response::error('Too many verification attempts. Please try again later.', 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';

    if (empty($code)) {
        Response::badRequest('Verification code required');
    }

    $pendingUser = Auth::getPendingUser();
    if (!$pendingUser) {
        Response::badRequest('No pending login session');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND two_factor_code = ? AND two_factor_expires > UTC_TIMESTAMP()');
        $stmt->execute([$pendingUser['id'], $code]);
        $user = $stmt->fetch();

        if (!$user) {
            // Track failed attempts
            $attemptKey = '2fa-fail-' . $pendingUser['id'];
            $attemptFile = __DIR__ . '/../../cache/ratelimit/' . md5($attemptKey);
            $attempts = 0;

            if (file_exists($attemptFile)) {
                $data = json_decode(file_get_contents($attemptFile), true);
                $attempts = $data['attempts'] ?? 0;
            }

            $attempts++;
            file_put_contents($attemptFile, json_encode(['attempts' => $attempts, 'last_attempt' => time()]));

            // Invalidate code after 5 wrong attempts
            if ($attempts >= 5) {
                $updateStmt = $db->prepare('UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?');
                $updateStmt->execute([$pendingUser['id']]);
                unlink($attemptFile);
                Response::unauthorized('Too many failed attempts. Please login again.');
            }

            Response::unauthorized('Invalid or expired verification code');
        }

        // Clear the 2FA code
        $updateStmt = $db->prepare('UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?');
        $updateStmt->execute([$user['id']]);

        // Complete the login
        Auth::complete2FA();
        Auth::regenerate();

        Response::json([
            'message' => 'Login successful',
            'user' => Auth::user()
        ]);

    } catch (Exception $e) {
        error_log('2FA verify error: ' . $e->getMessage());
        Response::serverError('Verification failed');
    }
}

// Handle POST /api/enable-2fa
if ($method === 'POST' && strpos($path, 'enable-2fa') !== false) {
    Auth::requireAuth();

    try {
        $db = Db::getInstance()->getConnection();

        // Generate 2FA code
        $code = Token::generate2FACode();
        $expiresAt = Token::get2FAExpiry(); // Use UTC time

        // Store code
        $updateStmt = $db->prepare('UPDATE users SET two_factor_code = ?, two_factor_expires = ? WHERE id = ?');
        $updateStmt->execute([$code, $expiresAt, Auth::user()['id']]);

        // Get user email
        $stmt = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([Auth::user()['id']]);
        $user = $stmt->fetch();

        // Send email
        $emailSent = Mailer::send2FACode($user['email'], $code, 'enable');

        if (!$emailSent && DEV_MODE) {
            error_log("DEV MODE: 2FA Code for {$user['email']}: $code");
        }

        Response::json([
            'message' => 'Verification code sent to your email',
            'email' => $user['email']
        ]);

    } catch (Exception $e) {
        error_log('2FA enable error: ' . $e->getMessage());
        Response::serverError('Failed to send verification code');
    }
}

// Handle POST /api/confirm-2fa
if ($method === 'POST' && strpos($path, 'confirm-2fa') !== false) {
    Auth::requireAuth();

    $input = json_decode(file_get_contents('php://input'), true);
    $code = $input['code'] ?? '';

    if (empty($code)) {
        Response::badRequest('Verification code required');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT * FROM users WHERE id = ? AND two_factor_code = ? AND two_factor_expires > UTC_TIMESTAMP()');
        $stmt->execute([Auth::user()['id'], $code]);
        $user = $stmt->fetch();

        if (!$user) {
            // Track failed attempts
            $attemptKey = '2fa-confirm-fail-' . Auth::user()['id'];
            $attemptFile = __DIR__ . '/../../cache/ratelimit/' . md5($attemptKey);
            $attempts = 0;

            if (file_exists($attemptFile)) {
                $data = json_decode(file_get_contents($attemptFile), true);
                $attempts = $data['attempts'] ?? 0;
            }

            $attempts++;
            file_put_contents($attemptFile, json_encode(['attempts' => $attempts, 'last_attempt' => time()]));

            // Invalidate code after 5 wrong attempts
            if ($attempts >= 5) {
                $updateStmt = $db->prepare('UPDATE users SET two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?');
                $updateStmt->execute([Auth::user()['id']]);
                unlink($attemptFile);
                Response::unauthorized('Too many failed attempts. Please enable 2FA again.');
            }

            Response::unauthorized('Invalid or expired verification code');
        }

        // Clear attempt tracking
        $attemptKey = '2fa-confirm-fail-' . Auth::user()['id'];
        $attemptFile = __DIR__ . '/../../cache/ratelimit/' . md5($attemptKey);
        if (file_exists($attemptFile)) {
            unlink($attemptFile);
        }

        // Enable 2FA and clear the code
        $updateStmt = $db->prepare('UPDATE users SET two_factor_enabled = 1, two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?');
        $updateStmt->execute([$user['id']]);

        Response::json([
            'message' => 'Two-factor authentication enabled successfully'
        ]);

    } catch (Exception $e) {
        error_log('2FA confirm error: ' . $e->getMessage());
        Response::serverError('Failed to enable 2FA');
    }
}

// Handle POST /api/disable-2fa
if ($method === 'POST' && strpos($path, 'disable-2fa') !== false) {
    Auth::requireAuth();

    try {
        $db = Db::getInstance()->getConnection();

        $updateStmt = $db->prepare('UPDATE users SET two_factor_enabled = 0, two_factor_code = NULL, two_factor_expires = NULL WHERE id = ?');
        $updateStmt->execute([Auth::user()['id']]);

        Response::json([
            'message' => 'Two-factor authentication disabled successfully'
        ]);

    } catch (Exception $e) {
        error_log('2FA disable error: ' . $e->getMessage());
        Response::serverError('Failed to disable 2FA');
    }
}

// Handle GET /api/user/2fa-status
if ($method === 'GET' && strpos($path, '2fa-status') !== false) {
    Auth::requireAuth();

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('SELECT two_factor_enabled FROM users WHERE id = ?');
        $stmt->execute([Auth::user()['id']]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::json([
            'two_factor_enabled' => (bool)$user['two_factor_enabled']
        ]);

    } catch (Exception $e) {
        error_log('2FA status error: ' . $e->getMessage());
        Response::serverError('Failed to get 2FA status');
    }
}

Response::notFound('Endpoint not found');
