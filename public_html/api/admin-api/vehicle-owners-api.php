<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    try { $pdo->exec("ALTER TABLE Users ADD COLUMN vehicle_number VARCHAR(255) NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE Users ADD COLUMN vehicle_type VARCHAR(255) NULL"); } catch (Exception $e) {}
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 5;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, email, phone, status, created_at, vehicle_number, vehicle_type 
                FROM Users 
                WHERE role = 'Vehicle Owner'";
        $params = [];
        
        if (isset($_GET['status']) && $_GET['status'] !== 'All' && $_GET['status'] !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        if (isset($_GET['sort']) && $_GET['sort'] === 'true') {
            $sql .= " ORDER BY created_at ASC, id ASC";
        } else {
            $sql .= " ORDER BY id DESC";
        }
        
        $countSql = "SELECT COUNT(*) as total FROM Users WHERE role = 'Vehicle Owner'";
        if (isset($_GET['status']) && $_GET['status'] !== 'All' && $_GET['status'] !== '') {
            $countSql .= " AND status = :status";
        }
        
        $stmtCount = $pdo->prepare($countSql);
        foreach($params as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        $sql .= " LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
    elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 'Pending';
        $stmt = $pdo->prepare("UPDATE Users SET status = :status WHERE id = :id AND role = 'Vehicle Owner'");
        $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
        echo json_encode(['success' => true]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
