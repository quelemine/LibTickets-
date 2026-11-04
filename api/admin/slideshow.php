<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=sql200.infinityfree.com;dbname=if0_43339001_libtickets;charset=utf8mb4',
            'if0_43339001',
            '3VtGM3Qxc1MXm8w',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

try {
    $db = getDb();
    
    // Get method - allow override via _method parameter for InfinityFree compatibility
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($_GET['_method'])) {
        $method = strtoupper($_GET['_method']);
    }
    
    // Parse ID from URL path or query parameter
    $path = $_SERVER['REQUEST_URI'];
    $imageId = null;
    
    if (isset($_GET['id'])) {
        $imageId = (int)$_GET['id'];
    } elseif (preg_match('#/slideshow/(\d+)#', $path, $matches)) {
        $imageId = (int)$matches[1];
    }
    
    // GET /api/admin/slideshow - Get all slideshow images
    if ($method === 'GET' && !$imageId) {
        try {
            $stmt = $db->query("SELECT * FROM slideshow_images ORDER BY order_num ASC, id ASC");
            $images = $stmt->fetchAll();

            foreach ($images as &$image) {
                $image['id'] = (int)$image['id'];
                $image['order_num'] = (int)$image['order_num'];
                $image['active'] = (int)$image['active'];
            }

            echo json_encode($images);
        } catch (Exception $e) {
            echo json_encode([]);
        }
        exit;
    }

    // GET /api/admin/slideshow?id=X - Get single slideshow image
    if ($method === 'GET' && $imageId) {
        $stmt = $db->prepare("SELECT * FROM slideshow_images WHERE id = ?");
        $stmt->execute([$imageId]);
        $image = $stmt->fetch();

        if (!$image) {
            http_response_code(404);
            echo json_encode(['error' => 'Image not found']);
            exit;
        }

        $image['id'] = (int)$image['id'];
        $image['order_num'] = (int)$image['order_num'];
        $image['active'] = (int)$image['active'];

        echo json_encode($image);
        exit;
    }
    
    // DELETE /api/admin/slideshow?id=X&_method=DELETE
    if ($method === 'DELETE' && $imageId) {
        $stmt = $db->prepare("DELETE FROM slideshow_images WHERE id = ?");
        $stmt->execute([$imageId]);
        
        echo json_encode(['success' => true, 'message' => 'Slideshow image deleted']);
        exit;
    }
    
    // PUT /api/admin/slideshow?id=X&_method=PUT
    if ($method === 'PUT' && $imageId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $updateFields = [];
        $updateValues = [];
        
        if (isset($data['image_url'])) {
            $updateFields[] = "image_url = ?";
            $updateValues[] = $data['image_url'];
        }
        if (isset($data['title'])) {
            $updateFields[] = "title = ?";
            $updateValues[] = $data['title'];
        }
        if (isset($data['order'])) {
            $updateFields[] = "order_num = ?";
            $updateValues[] = (int)$data['order'];
        }
        if (isset($data['active'])) {
            $updateFields[] = "active = ?";
            $updateValues[] = $data['active'] ? 1 : 0;
        }
        
        $updateValues[] = $imageId;
        
        $sql = "UPDATE slideshow_images SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute($updateValues);
        
        echo json_encode(['success' => true, 'message' => 'Slideshow image updated']);
        exit;
    }
    
    // POST /api/admin/slideshow
    if ($method === 'POST' && !$imageId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("INSERT INTO slideshow_images (image_url, title, order_num, active) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['image_url'],
            $data['title'],
            (int)$data['order'],
            $data['active'] ? 1 : 0
        ]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Slideshow image created']);
        exit;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found', 'method' => $method, 'id' => $imageId]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
