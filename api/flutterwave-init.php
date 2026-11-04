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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Flutterwave API credentials
// Get these from: https://dashboard.flutterwave.com/dashboard/settings/apis
$FLW_PUBLIC_KEY = 'FLWPUBK-YOUR-PUBLIC-KEY'; // Replace with your public key
$FLW_SECRET_KEY = 'FLWSECK-YOUR-SECRET-KEY'; // Replace with your secret key
$FLW_ENCRYPTION_KEY = 'YOUR-ENCRYPTION-KEY'; // Replace with your encryption key

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['amount', 'currency', 'customer_email', 'customer_name', 'customer_phone', 'booking_data'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Generate transaction reference
    $txRef = 'LIB-' . uniqid() . '-' . time();
    
    // Prepare payment data
    $paymentData = [
        'tx_ref' => $txRef,
        'amount' => (float)$data['amount'],
        'currency' => $data['currency'],
        'redirect_url' => 'http://libtickets.gt.tc/payment-callback.html',
        'payment_options' => 'card,mobilemoney,ussd,banktransfer',
        'customer' => [
            'email' => $data['customer_email'],
            'name' => $data['customer_name'],
            'phonenumber' => $data['customer_phone']
        ],
        'customizations' => [
            'title' => 'LibTickets',
            'description' => 'Ticket Purchase',
            'logo' => 'http://libtickets.gt.tc/assets/logo.png'
        ],
        'meta' => [
            'booking_data' => json_encode($data['booking_data'])
        ]
    ];
    
    // Initialize payment with Flutterwave
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.flutterwave.com/v3/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $FLW_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Payment initialization failed');
    }
    
    $result = json_decode($response, true);
    
    if ($result['status'] !== 'success') {
        throw new Exception($result['message'] ?? 'Payment initialization failed');
    }
    
    // Return payment link
    echo json_encode([
        'success' => true,
        'payment_link' => $result['data']['link'],
        'tx_ref' => $txRef
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
