<?php
/**
 * Logout endpoint
 * POST /api/logout
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

Auth::logout();

Response::json(['message' => 'Logged out successfully']);
