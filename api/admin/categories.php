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
    
    // Get method - allow override via _method parameter
    $method = $_SERVER['REQUEST_METHOD'];
    if (isset($_GET['_method'])) {
        $method = strtoupper($_GET['_method']);
    }
    
    // Parse ID from URL path or query parameter
    $path = $_SERVER['REQUEST_URI'];
    $categoryId = null;
    
    if (isset($_GET['id'])) {
        $categoryId = (int)$_GET['id'];
    } elseif (preg_match('#/categories/(\d+)#', $path, $matches)) {
        $categoryId = (int)$matches[1];
    }
    
    // PUT /api/admin/categories?id=X&_method=PUT
    if ($method === 'PUT' && $categoryId) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $stmt = $db->prepare("UPDATE categories SET image_url = ? WHERE id = ?");
        $stmt->execute([
            $data['image_url'],
            $categoryId
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Category updated']);
        exit;
    }
    
    http_response_code(404);
    echo json_encode(['error' => 'Endpoint not found']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
