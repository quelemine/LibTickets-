<?php
/**
 * Registration endpoints
 * POST /api/signup (legacy)
 * POST /api/register (full registration)
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
$path = $_SERVER['REQUEST_URI'];

// Handle POST /api/signup (legacy)
if ($method === 'POST' && strpos($path, 'signup') !== false && strpos($path, 'register') === false) {
    // Rate limiting (10 attempts per 15 minutes)
    $ip = RateLimiter::getClientIP();
    if (!RateLimiter::check($ip, 'signup', 10, 900)) {
        Response::error('Too many registration attempts. Please try again later.', 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::badRequest('Invalid JSON');
    }

    $name = $input['name'] ?? '';
    $email = Validator::sanitizeEmail($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        Response::badRequest('Email and password required');
    }

    if (!Validator::email($email)) {
        Response::badRequest('Invalid email format');
    }

    if (strlen($password) < 6) {
        Response::badRequest('Password must be at least 6 characters');
    }

    try {
        $db = Db::getInstance()->getConnection();

        // Check if user exists
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::badRequest('Email already registered');
        }

        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $insertStmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
        $insertStmt->execute([$email, $passwordHash, 'customer']);
        $userId = (int)$db->lastInsertId();

        Response::json([
            'message' => 'Account created successfully',
            'userId' => $userId
        ]);

    } catch (Exception $e) {
        error_log('Signup error: ' . $e->getMessage());
        Response::serverError('Registration failed');
    }
}

// Handle POST /api/register (full registration)
if ($method === 'POST' && strpos($path, 'register') !== false && strpos($path, 'signup') === false) {
    // Rate limiting (10 attempts per 15 minutes)
    $ip = RateLimiter::getClientIP();
    if (!RateLimiter::check($ip, 'register', 10, 900)) {
        Response::error('Too many registration attempts. Please try again later.', 429);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        Response::badRequest('Invalid JSON');
    }

    $fullName = Validator::sanitizeString($input['full_name'] ?? '');
    $email = Validator::sanitizeEmail($input['email'] ?? '');
    $phone = Validator::sanitizeString($input['phone'] ?? '');
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    $termsAccepted = $input['terms_accepted'] ?? false;

    // Validation
    if (empty($fullName) || empty($email) || empty($phone) || empty($password) || empty($confirmPassword)) {
        Response::badRequest('All fields are required');
    }

    if (!Validator::email($email)) {
        Response::badRequest('Invalid email format');
    }

    if (strlen($password) < 8) {
        Response::badRequest('Password must be at least 8 characters');
    }

    if (!Validator::passwordMatch($password, $confirmPassword)) {
        Response::badRequest('Passwords do not match');
    }

    if (!$termsAccepted) {
        Response::badRequest('You must accept the terms and conditions');
    }

    try {
        $db = Db::getInstance()->getConnection();

        // Check if email exists
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            Response::badRequest('Email already registered');
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Create user
        $insertStmt = $db->prepare('INSERT INTO users (email, password_hash, role, full_name, phone, is_verified) VALUES (?, ?, ?, ?, ?, ?)');
        $insertStmt->execute([$email, $passwordHash, 'customer', $fullName, $phone, 0]);
        $userId = (int)$db->lastInsertId();

        // Generate verification token
        $token = Token::generate();
        $tokenHash = Token::hash($token);
        $expiresAt = Token::getEmailVerificationExpiry();

        $tokenStmt = $db->prepare('INSERT INTO email_verification_tokens (user_id, token, expires_at) VALUES (?, ?, ?)');
        $tokenStmt->execute([$userId, $tokenHash, $expiresAt]);

        // Send verification email
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/verify-email';
        $emailSent = Mailer::sendEmailVerification($email, $fullName, $token, $baseUrl);

        if (!$emailSent && DEV_MODE) {
            error_log("DEV MODE: Email Verification Link: $baseUrl?token=$token");
        }

        Response::json([
            'message' => 'Registration successful. Please check your email to verify your account.',
            'userId' => $userId,
            'requiresVerification' => true
        ]);

    } catch (Exception $e) {
        error_log('Registration error: ' . $e->getMessage());
        Response::serverError('Registration failed');
    }
}

Response::notFound('Endpoint not found');
