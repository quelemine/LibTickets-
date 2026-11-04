<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

try {
    session_start();
    $user = $_SESSION['user'] ?? null;
    echo json_encode(['user' => $user]);
} catch (Exception $e) {
    echo json_encode(['user' => null]);
}
?>
