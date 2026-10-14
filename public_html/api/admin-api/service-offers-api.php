<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    // Create ServiceOffers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS ServiceOffers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        audience VARCHAR(100) NOT NULL,
        start_date DATE NOT NULL,
        expiry_date DATE NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Auto-update statuses globally
    $pdo->exec("UPDATE ServiceOffers SET status = 'Active' WHERE CURDATE() >= start_date AND CURDATE() <= expiry_date");
    $pdo->exec("UPDATE ServiceOffers SET status = 'Scheduled' WHERE start_date > CURDATE()");
    $pdo->exec("UPDATE ServiceOffers SET status = 'Expired' WHERE expiry_date < CURDATE()");

    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        $status = $_GET['status'] ?? 'All Offers';
        $sort = $_GET['sort'] ?? 'newest';
        
        $sql = "SELECT * FROM ServiceOffers";
        $params = [];
        
        if ($status !== 'All Offers') {
            $sql .= " WHERE status = :status";
            $params[':status'] = $status;
        }
        
        if ($sort === 'newest') {
            $sql .= " ORDER BY created_at DESC";
        } elseif ($sort === 'oldest') {
            $sql .= " ORDER BY created_at ASC";
        } elseif ($sort === 'title') {
            $sql .= " ORDER BY title ASC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $offers = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'data' => $offers]);
    }
    elseif ($action === 'add' || $action === 'update') {
        header('Content-Type: application/json; charset=utf-8');
        
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $audience = trim($_POST['audience'] ?? '');
        $start_date = $_POST['start_date'] ?? date('Y-m-d');
        $expiry_date = $_POST['expiry_date'] ?? date('Y-m-d');
        
        $today = date('Y-m-d');
        $status = 'Active';
        if ($start_date > $today) $status = 'Scheduled';
        if ($expiry_date < $today) $status = 'Expired';
        
        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO ServiceOffers (title, description, audience, start_date, expiry_date, status) VALUES (:title, :desc, :aud, :sd, :ed, :st)");
            $stmt->execute([
                ':title' => $title, ':desc' => $desc, ':aud' => $audience, 
                ':sd' => $start_date, ':ed' => $expiry_date, ':st' => $status
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE ServiceOffers SET title=:title, description=:desc, audience=:aud, start_date=:sd, expiry_date=:ed, status=:st WHERE id=:id");
            $stmt->execute([
                ':title' => $title, ':desc' => $desc, ':aud' => $audience, 
                ':sd' => $start_date, ':ed' => $expiry_date, ':st' => $status, ':id' => $id
            ]);
        }
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'delete') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM ServiceOffers WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
