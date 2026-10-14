<?php
header('Content-Type: application/json');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM ServiceOffers ORDER BY created_at DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO ServiceOffers (title, description, discount_percentage, valid_until, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'] ?? '',
            $data['description'] ?? '',
            $data['discount_percentage'] ?? 0,
            $data['valid_until'] ?? null,
            $data['status'] ?? 'Active'
        ]);
        echo json_encode(['success' => true, 'message' => 'Offer created successfully']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
