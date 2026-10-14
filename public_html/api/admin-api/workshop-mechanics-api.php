<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    // Ensure columns exist (Fail softly if they already exist or can't be added)
    try { $pdo->exec("ALTER TABLE Users ADD COLUMN specialization VARCHAR(255) DEFAULT 'General'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE Users ADD COLUMN experience INT DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE Users ADD COLUMN workshop_id INT NULL"); } catch (Exception $e) {}
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'workshops') {
        $stmt = $pdo->query("SELECT id, name FROM Workshops ORDER BY name ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    elseif ($action === 'list') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 5;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT Users.id, Users.name, Users.email, Users.phone, Users.status, Users.created_at, Users.specialization, Users.experience, Workshops.name as workshop_name, Users.workshop_id 
                FROM Users 
                LEFT JOIN Workshops ON Users.workshop_id = Workshops.id 
                WHERE Users.role = 'Mechanic'";
        $params = [];
        
        if (isset($_GET['status']) && $_GET['status'] !== 'All' && $_GET['status'] !== '') {
            $sql .= " AND Users.status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        if (isset($_GET['sort']) && $_GET['sort'] === 'true') {
            $sql .= " ORDER BY Users.experience DESC, CASE Users.status WHEN 'Active' THEN 1 WHEN 'Pending' THEN 2 WHEN 'Suspended' THEN 3 ELSE 4 END ASC, Users.id DESC";
        } else {
            $sql .= " ORDER BY Users.id DESC";
        }
        
        $countSql = "SELECT COUNT(*) as total FROM Users WHERE role = 'Mechanic'";
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
    elseif ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'Pending';
        $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
        $specialization = $_POST['specialization'] ?? 'General';
        $experience = (int)($_POST['experience'] ?? 0);
        $workshop_id = $_POST['workshop_id'] ?? null;
        if($workshop_id === '') $workshop_id = null;
        
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, phone, role, status, created_at, specialization, experience, workshop_id) VALUES (:name, :email, :phone, 'Mechanic', :status, :created_at, :specialization, :experience, :workshop_id)");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':status' => $status,
            ':created_at' => $joining_date . ' ' . date('H:i:s'),
            ':specialization' => $specialization,
            ':experience' => $experience,
            ':workshop_id' => $workshop_id
        ]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update_status') {
        $id = $_POST['id'] ?? 0;
        $status = $_POST['status'] ?? 'Pending';
        $stmt = $pdo->prepare("UPDATE Users SET status = :status WHERE id = :id AND role = 'Mechanic'");
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
