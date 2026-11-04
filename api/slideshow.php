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
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Check if ID is in the URL path
    $path = $_SERVER['REQUEST_URI'];
    if (preg_match('#/slideshow\.php/(\d+)#', $path, $matches)) {
        // Get single slideshow image by ID
        $imageId = (int)$matches[1];

        $stmt = $pdo->prepare("SELECT * FROM slideshow_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();

        if (!$image) {
            http_response_code(404);
            echo json_encode(['error' => 'Image not found']);
            exit;
        }

        $image['id'] = (int)$image['id'];
        $image['order_num'] = (int)$image['order_num'];
        $image['active'] = (bool)$image['active'];

        echo json_encode($image);
        exit;
    }

    // Get all active slideshow images
    try {
        $stmt = $pdo->query('SELECT * FROM slideshow_images WHERE active = 1 ORDER BY order_num, id');
        $images = $stmt->fetchAll();
    } catch (Exception $e) {
        // Table might not exist, return empty array
        echo json_encode([]);
        exit;
    }

    foreach ($images as &$image) {
        $image['id'] = (int)$image['id'];
        $image['order_num'] = (int)$image['order_num'];
        $image['active'] = (bool)$image['active'];
    }

    echo json_encode($images);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Slideshow API Error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
?>
