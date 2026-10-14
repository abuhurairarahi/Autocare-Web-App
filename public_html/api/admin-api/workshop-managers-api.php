<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, email, phone, status, created_at, role, (SELECT name FROM Workshops WHERE manager_id = Users.id LIMIT 1) as workshop_name, (SELECT id FROM Workshops WHERE manager_id = Users.id LIMIT 1) as workshop_id FROM Users WHERE role = 'Manager'";
        $params = [];
        
        if (isset($_GET['status']) && $_GET['status'] !== 'All' && $_GET['status'] !== '') {
            $sql .= " AND status = :status";
            $params[':status'] = $_GET['status'];
        }
        
        if (isset($_GET['search']) && trim($_GET['search']) !== '') {
            $searchTerm = trim($_GET['search']);
            $idSearch = preg_replace('/[^0-9]/', '', $searchTerm);
            
            if ($idSearch !== '') {
                $sql .= " AND (name LIKE :search OR id = :id_search)";
                $params[':id_search'] = $idSearch;
            } else {
                $sql .= " AND name LIKE :search";
            }
            $params[':search'] = '%' . $searchTerm . '%';
        }
        
        $countSql = str_replace("SELECT id, name, email, phone, status, created_at, role, (SELECT name FROM Workshops WHERE manager_id = Users.id LIMIT 1) as workshop_name, (SELECT id FROM Workshops WHERE manager_id = Users.id LIMIT 1) as workshop_id", "SELECT COUNT(*) as total", $sql);
        $stmtCount = $pdo->prepare($countSql);
        foreach($params as $key => $val) {
            $stmtCount->bindValue($key, $val);
        }
        $stmtCount->execute();
        $total = (int)$stmtCount->fetchColumn();
        
        $sql .= " ORDER BY id DESC LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $managers = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $managers, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
    elseif ($action === 'add') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        $joining_date = $_POST['joining_date'] ?? date('Y-m-d');
        
        $workshop_id = isset($_POST['workshop_id']) && $_POST['workshop_id'] !== '' ? (int)$_POST['workshop_id'] : null;
        
        $stmt = $pdo->prepare("INSERT INTO Users (name, email, phone, role, status, created_at, workshop_id) VALUES (:name, :email, :phone, 'Manager', :status, :created_at, :workshop_id)");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':status' => $status,
            ':created_at' => $joining_date . ' ' . date('H:i:s'),
            ':workshop_id' => $workshop_id
        ]);
        
        $newManagerId = $pdo->lastInsertId();
        $workshop_id = $_POST['workshop_id'] ?? '';
        if ($workshop_id !== '') {
            $stmt = $pdo->prepare("UPDATE Workshops SET manager_id = :manager_id WHERE id = :workshop_id");
            $stmt->execute([':manager_id' => $newManagerId, ':workshop_id' => $workshop_id]);
        }
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update') {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $status = $_POST['status'] ?? 'Active';
        
        $workshop_id = isset($_POST['workshop_id']) && $_POST['workshop_id'] !== '' ? (int)$_POST['workshop_id'] : null;
        
        $stmt = $pdo->prepare("UPDATE Users SET name = :name, email = :email, phone = :phone, status = :status, workshop_id = :workshop_id WHERE id = :id AND role = 'Manager'");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':status' => $status,
            ':workshop_id' => $workshop_id,
            ':id' => $id
        ]);
        
        // Remove manager from their current workshop
        $stmt = $pdo->prepare("UPDATE Workshops SET manager_id = NULL WHERE manager_id = :manager_id");
        $stmt->execute([':manager_id' => $id]);
        
        // Assign manager to new workshop
        if ($workshop_id !== '') {
            $stmt = $pdo->prepare("UPDATE Workshops SET manager_id = :manager_id WHERE id = :workshop_id");
            $stmt->execute([':manager_id' => $id, ':workshop_id' => $workshop_id]);
        }
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'list_workshops') {
        $stmt = $pdo->prepare("SELECT id, name FROM Workshops ORDER BY name ASC");
        $stmt->execute();
        $workshops = $stmt->fetchAll();
        echo json_encode(['success' => true, 'data' => $workshops]);
    }
    elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM Users WHERE id = :id AND role = 'Manager'");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
