<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    echo json_encode(['rate' => 200]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exchange Rate API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
