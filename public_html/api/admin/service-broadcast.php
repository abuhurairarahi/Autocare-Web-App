<?php
header('Content-Type: application/json');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("SELECT * FROM ServiceBroadcasts ORDER BY created_at DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO ServiceBroadcasts (title, content, audience, priority, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['audience'] ?? 'All',
            $data['priority'] ?? 'Low',
            $data['status'] ?? 'Draft'
        ]);
        echo json_encode(['success' => true, 'message' => 'Broadcast created successfully']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
