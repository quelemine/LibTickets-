<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Return default logo or settings logo
echo json_encode([
    'url' => '/images/libtickets_logo.png'
]);
?>
