<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
        'if0_43339001',
        '3VtGM3Qxc1MXm8w',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Update slideshow images to use actual files that exist
    // Get current images
    $stmt = $pdo->query("SELECT id, order_num FROM slideshow_images ORDER BY order_num");
    $images = $stmt->fetchAll();

    // Map based on order to existing files
    $actualFiles = [
        1 => 'highlight1.jpeg',
        2 => 'highlight3.jpeg',
        3 => 'highlight5.jpeg',
        4 => 'highlight6.jpeg'
    ];

    foreach ($images as $image) {
        $order = $image['order_num'];
        if (isset($actualFiles[$order])) {
            $updateStmt = $pdo->prepare("UPDATE slideshow_images SET image_url = ? WHERE id = ?");
            $updateStmt->execute(['/images/' . $actualFiles[$order], $image['id']]);
        }
    }

    echo json_encode(['success' => true, 'message' => 'Database updated with correct image filenames']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
