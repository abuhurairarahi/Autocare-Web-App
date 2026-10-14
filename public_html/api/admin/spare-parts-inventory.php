<?php
header('Content-Type: application/json');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM SpareParts ORDER BY name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO SpareParts (workshop_id, sku, name, category, price, stock_quantity, supplier) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['workshop_id'] ?? 1,
            $data['sku'] ?? 'SKU-'.rand(1000,9999),
            $data['name'] ?? '',
            $data['category'] ?? '',
            $data['price'] ?? 0,
            $data['stock_quantity'] ?? 0,
            $data['supplier'] ?? ''
        ]);
        echo json_encode(['success' => true, 'message' => 'Part added successfully']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
