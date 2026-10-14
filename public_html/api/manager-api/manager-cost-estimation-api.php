<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    // Ensure tables exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS estimates (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        code VARCHAR(50), 
        job_card_id INT, 
        customer_name VARCHAR(100), 
        mechanic_name VARCHAR(100), 
        status VARCHAR(50) DEFAULT 'Draft', 
        sent_date VARCHAR(50) DEFAULT '-', 
        subtotal DECIMAL(10,2) DEFAULT 0, 
        tax_amount DECIMAL(10,2) DEFAULT 0, 
        total_estimated_cost DECIMAL(10,2) DEFAULT 0, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS estimateitems (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        estimate_id INT, 
        description VARCHAR(255), 
        qty DECIMAL(10,2) DEFAULT 1, 
        unit_price DECIMAL(10,2) DEFAULT 0, 
        total DECIMAL(10,2) DEFAULT 0
    )");

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $query = "
            SELECT e.id, e.code, e.job_card_id, e.status, e.sent_date, e.total_estimated_cost,
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS job_card_code, 
                   u_cust.id AS customer_id,
                   u_cust.name AS customer_name,
                   u_mech.name AS mechanic_name,
                   CONCAT(v.make, ' ', v.model) AS job_card_title
            FROM estimates e 
            LEFT JOIN jobcards j ON e.job_card_id = j.id 
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN users u_mech ON j.mechanic_id = u_mech.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            ORDER BY e.id DESC
        ";
        $estimates = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'estimates' => $estimates]);
    }
    elseif ($action === 'details') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? 0);
        
        $query = "
            SELECT e.id, e.code, e.job_card_id, e.status, e.sent_date, e.total_estimated_cost,
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS job_card_code, 
                   u_cust.id AS customer_id,
                   u_cust.name AS customer_name,
                   u_mech.name AS mechanic_name,
                   CONCAT(v.make, ' ', v.model) AS job_card_title
            FROM estimates e 
            LEFT JOIN jobcards j ON e.job_card_id = j.id 
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN users u_mech ON j.mechanic_id = u_mech.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            WHERE e.id = ?
        ";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$id]);
        $est = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($est) {
            $stmtItems = $pdo->prepare("SELECT * FROM estimateitems WHERE estimate_id = ?");
            $stmtItems->execute([$id]);
            $est['line_items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'estimate' => $est]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Not found']);
        }
    }
    elseif ($action === 'create') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $jobId = (int)($data['job_card_id'] ?? 0);
        
        // Verify job card exists
        $stmt = $pdo->prepare("SELECT id FROM jobcards WHERE id = ?");
        $stmt->execute([$jobId]);
        if ($stmt->fetchColumn()) {
            $code = 'EST-' . date('Y') . '-' . rand(1000, 9999);
            // We ignore customer_name and mechanic_name columns since they are joined dynamically now,
            // but we'll insert dummy data to avoid errors if they are NOT NULL, though they are YES for null.
            $stmtInsert = $pdo->prepare("INSERT INTO estimates (code, job_card_id) VALUES (?, ?)");
            $stmtInsert->execute([$code, $jobId]);
            $estId = $pdo->lastInsertId();
            
            echo json_encode(['success' => true, 'id' => $estId]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Job card not found']);
        }
    }
    elseif ($action === 'add_item') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $estId = (int)($data['estimate_id'] ?? 0);
        $desc = trim($data['description'] ?? '');
        $qty = (float)($data['qty'] ?? 1);
        $price = (float)($data['unit_price'] ?? 0);
        $total = $qty * $price;
        
        if ($estId > 0 && !empty($desc)) {
            $stmt = $pdo->prepare("INSERT INTO estimateitems (estimate_id, description, qty, unit_price, total) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$estId, $desc, $qty, $price, $total]);
            recalcEstimate($pdo, $estId);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
        }
    }
    elseif ($action === 'remove_item') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $itemId = (int)($data['item_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT estimate_id FROM estimateitems WHERE id = ?");
        $stmt->execute([$itemId]);
        $estId = $stmt->fetchColumn();
        
        if ($estId) {
            $pdo->prepare("DELETE FROM estimateitems WHERE id = ?")->execute([$itemId]);
            recalcEstimate($pdo, (int)$estId);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Item not found']);
        }
    }
    elseif ($action === 'update_status') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $estId = (int)($data['id'] ?? 0);
        $status = $data['status'] ?? 'Draft';
        $sentDate = $status === 'Sent' ? date('M d, Y') : '-';
        
        $stmt = $pdo->prepare("UPDATE estimates SET status = ?, sent_date = ? WHERE id = ?");
        $stmt->execute([$status, $sentDate, $estId]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'job_cards') {
        header('Content-Type: application/json; charset=utf-8');
        
        $query = "
            SELECT j.id, 
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS code, 
                   u_cust.id AS customer_id,
                   u_cust.name AS customer_name,
                   CONCAT(v.make, ' ', v.model) AS vehicle_title
            FROM jobcards j
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            WHERE j.status NOT IN ('Delivered', 'Ready', 'Completed') 
            ORDER BY j.id DESC
        ";
        $jobs = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'job_cards' => $jobs]);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function recalcEstimate(PDO $pdo, int $estId) {
    $stmt = $pdo->prepare("SELECT SUM(total) FROM estimateitems WHERE estimate_id = ?");
    $stmt->execute([$estId]);
    $subtotal = (float)$stmt->fetchColumn();
    
    $tax = $subtotal * 0.085;
    $grand = $subtotal + $tax;
    
    $stmtUpdate = $pdo->prepare("UPDATE estimates SET subtotal = ?, tax_amount = ?, total_estimated_cost = ? WHERE id = ?");
    $stmtUpdate->execute([$subtotal, $tax, $grand, $estId]);
}
