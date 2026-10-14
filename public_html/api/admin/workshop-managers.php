<?php
header('Content-Type: application/json');
require_once '../db.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("SELECT user_id, name, email, phone, created_at FROM Users WHERE role = 'Manager' ORDER BY created_at DESC");
        $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    } 
    elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, password_hash, role, phone) VALUES (?, ?, ?, 'Manager', ?)");
        $stmt->execute([
            $data['name'] ?? '',
            $data['email'] ?? '',
            password_hash($data['password'] ?? 'manager123', PASSWORD_DEFAULT),
            $data['phone'] ?? ''
        ]);
        echo json_encode(['success' => true, 'message' => 'Manager created successfully']);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
