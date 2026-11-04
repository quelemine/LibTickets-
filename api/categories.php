<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->query('SELECT * FROM categories ORDER BY name');
    $categories = $stmt->fetchAll();

    foreach ($categories as &$category) {
        $category['id'] = (int)$category['id'];
    }

    echo json_encode($categories);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Categories API Error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to fetch categories', 'message' => $e->getMessage()]);
}
?>
