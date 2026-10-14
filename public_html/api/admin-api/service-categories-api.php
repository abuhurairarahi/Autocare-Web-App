<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        $sql = "SELECT id, name, description, base_rate as base_price, status, created_at FROM ServiceCategories WHERE 1=1";
        $params = [];
        
        $status = $_GET['status'] ?? 'All';
        if ($status !== 'All' && $status !== 'Recently Added') {
            $sql .= " AND status = :status";
            $params[':status'] = $status;
        }
        
        if ($status === 'Recently Added') {
            $sql .= " ORDER BY created_at DESC, id DESC";
        } else {
            $sql .= " ORDER BY id ASC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $categories = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $categories]);
    }
    elseif ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        
        $stmt = $pdo->prepare("INSERT INTO ServiceCategories (name, description, base_rate, status) VALUES (:name, :description, :base_price, :status)");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':base_price' => $base_price,
            ':status' => $status
        ]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 0);
        $status = $_POST['status'] ?? 'Pending';
        
        $stmt = $pdo->prepare("UPDATE ServiceCategories SET name = :name, description = :description, base_rate = :base_price, status = :status WHERE id = :id");
        $stmt->execute([
            ':name' => $name,
            ':description' => $description,
            ':base_price' => $base_price,
            ':status' => $status,
            ':id' => $id
        ]);
        echo json_encode(['success' => true]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
