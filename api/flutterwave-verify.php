<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '../logs/php-errors.log');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Flutterwave API credentials
$FLW_SECRET_KEY = 'FLWSECK-YOUR-SECRET-KEY'; // Replace with your secret key

try {
    // Get transaction ID from query parameter
    $transactionId = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : null;
    $txRef = isset($_GET['tx_ref']) ? $_GET['tx_ref'] : null;
    
    if (!$transactionId) {
        throw new Exception('Transaction ID is required');
    }
    
    // Verify transaction with Flutterwave
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $FLW_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Transaction verification failed');
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] !== 'success') {
        throw new Exception('Transaction verification failed');
    }
    
    $transaction = $result['data'];
    
    // Check if payment was successful
    if ($transaction['status'] !== 'successful') {
        throw new Exception('Payment was not successful');
    }
    
    // Return verification result
    echo json_encode([
        'success' => true,
        'transaction' => [
            'id' => $transaction['id'],
            'tx_ref' => $transaction['tx_ref'],
            'amount' => $transaction['amount'],
            'currency' => $transaction['currency'],
            'status' => $transaction['status'],
            'customer' => $transaction['customer'],
            'booking_data' => isset($transaction['meta']['booking_data']) ? json_decode($transaction['meta']['booking_data'], true) : null
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
