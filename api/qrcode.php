<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    $data = $_GET['data'] ?? '';
    $size = isset($_GET['size']) ? (int)$_GET['size'] : 150;

    if (empty($data)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'No data provided']);
        exit;
    }

    // Redirect to reliable QR code API
    $qrUrl = "https://quickchart.io/qr?size={$size}x{$size}&text=" . urlencode($data);
    header('Location: ' . $qrUrl);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    error_log("QR Code Error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?>
