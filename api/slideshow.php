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

    // Check if slideshow_images table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'slideshow_images'");
    $tableExists = $stmt->rowCount() > 0;

    if (!$tableExists) {
        // Create the table
        $pdo->query("
            CREATE TABLE slideshow_images (
                id INT AUTO_INCREMENT PRIMARY KEY,
                image_url VARCHAR(500) NOT NULL,
                title VARCHAR(255),
                order_num INT DEFAULT 0,
                active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Insert default images with placeholder colors if table was just created
        $pdo->query("INSERT INTO slideshow_images (image_url, title, order_num, active) VALUES ('/images/highlight1.jpeg', 'Highlight 1', 1, 1)");
        $pdo->query("INSERT INTO slideshow_images (image_url, title, order_num, active) VALUES ('/images/highlight3.jpeg', 'Highlight 2', 2, 1)");
        $pdo->query("INSERT INTO slideshow_images (image_url, title, order_num, active) VALUES ('/images/highlight5.jpeg', 'Highlight 3', 3, 1)");
    }

    // Get all active slideshow images
    try {
        $stmt = $pdo->query('SELECT * FROM slideshow_images WHERE active = 1 ORDER BY order_num, id');
        $images = $stmt->fetchAll();
    } catch (Exception $e) {
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
