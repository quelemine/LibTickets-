<?php
/**
 * Session endpoint
 * GET /api/session
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Auth::startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('Method not allowed', 405);
}

$user = Auth::user();

Response::json(['user' => $user]);
