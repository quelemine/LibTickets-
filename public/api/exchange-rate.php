<?php
/**
 * Exchange rate endpoints
 * GET /api/exchange-rate - Get current exchange rate (USD to LRD)
 * POST /api/exchange-rate - Update exchange rate (admin only)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/Db.php';
require_once __DIR__ . '/../../src/Response.php';
require_once __DIR__ . '/../../src/Auth.php';

Auth::startSession();

$method = $_SERVER['REQUEST_METHOD'];

// GET /api/exchange-rate
if ($method === 'GET') {
    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            SELECT rate FROM exchange_rates 
            WHERE from_currency = ? AND to_currency = ? 
            ORDER BY effective_date DESC 
            LIMIT 1
        ');
        $stmt->execute(['USD', 'LRD']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::json([
            'rate' => $row ? (float)$row['rate'] : null
        ]);

    } catch (Exception $e) {
        error_log('Get exchange rate error: ' . $e->getMessage());
        Response::serverError('Failed to retrieve exchange rate');
    }
}

// POST /api/exchange-rate
if ($method === 'POST') {
    // Check if user is admin
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        Response::unauthorized('Admin access required');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $rate = $input['rate'] ?? null;

    if (!$rate || $rate <= 0) {
        Response::badRequest('Invalid exchange rate');
    }

    try {
        $db = Db::getInstance()->getConnection();

        $stmt = $db->prepare('
            INSERT INTO exchange_rates (from_currency, to_currency, rate) 
            VALUES (?, ?, ?)
        ');
        $stmt->execute(['USD', 'LRD', $rate]);

        error_log("Exchange rate updated to $rate by admin user ID: {$_SESSION['user']['id']}");

        Response::json([
            'message' => 'Exchange rate updated',
            'rate' => (float)$rate
        ]);

    } catch (Exception $e) {
        error_log('Update exchange rate error: ' . $e->getMessage());
        Response::serverError('Failed to update exchange rate');
    }
}

// Method not allowed
Response::error('Method not allowed', 405);
