<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    // Create Spare Parts Inventory
    $pdo->exec("CREATE TABLE IF NOT EXISTS SpareParts (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        name VARCHAR(100), 
        part_number VARCHAR(50), 
        price DECIMAL(10,2), 
        quantity_in_stock INT DEFAULT 0,
        low_stock_threshold INT DEFAULT 5
    )");

    // Create Spare Part Requests
    $pdo->exec("CREATE TABLE IF NOT EXISTS SparePartRequests (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        job_card_id INT, 
        work_order VARCHAR(50),
        part_id INT, 
        part_name VARCHAR(100), 
        part_number VARCHAR(50), 
        quantity INT DEFAULT 1, 
        unit VARCHAR(20) DEFAULT 'Units',
        unit_price DECIMAL(10,2), 
        total_price DECIMAL(10,2), 
        mechanic_name VARCHAR(100),
        status VARCHAR(50) DEFAULT 'Pending Approval', 
        rejection_reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed Spare Parts if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM SpareParts")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO SpareParts (name, part_number, price, quantity_in_stock, low_stock_threshold) VALUES 
            ('Premium Synthetic Engine Oil (5W-30)', 'PN: LUB-5W30-SYN', 450.00, 150, 20),
            ('Ceramic Brake Pads (Front Set)', 'PN: BRK-CER-F-09', 2450.00, 4, 10),
            ('Heavy-Duty Transmission Fluid', 'PN: TRN-FL-HD', 850.00, 85, 15),
            ('Iridium Spark Plug Set (x4)', 'PN: SPK-IRD-X4', 3200.00, 12, 10),
            ('Cabin Air Filter (Activated Carbon)', 'PN: FLT-CAB-AC', 650.00, 40, 15)");
    }

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $requests = $pdo->query("SELECT * FROM SparePartRequests ORDER BY id DESC")->fetchAll();
        $parts = $pdo->query("SELECT id, name, sku as part_number, price, stock_quantity as quantity_in_stock, reorder_level as low_stock_threshold FROM SpareParts")->fetchAll();
        
        echo json_encode(['success' => true, 'requests' => $requests, 'parts' => $parts]);
    }
    elseif ($action === 'create') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $jobId = (int)($data['job_card_id'] ?? 0);
        $workOrder = $data['work_order'] ?? '#WO-Unknown';
        $partId = (int)($data['part_id'] ?? 0);
        $partName = $data['part_name'] ?? 'Spare Part';
        $partNumber = $data['part_number'] ?? 'N/A';
        $qty = (int)($data['quantity'] ?? 1);
        $unitPrice = (float)($data['unit_price'] ?? 0);
        $totalPrice = $qty * $unitPrice;
        $mechName = $data['mechanic_name'] ?? 'Mechanic';
        
        $stmt = $pdo->prepare("INSERT INTO SparePartRequests (job_card_id, work_order, part_id, part_name, part_number, quantity, unit_price, total_price, mechanic_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$jobId, $workOrder, $partId, $partName, $partNumber, $qty, $unitPrice, $totalPrice, $mechName]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'approve') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT part_id, quantity FROM SparePartRequests WHERE id = ? AND status = 'Pending Approval'");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        
        if ($req) {
            // Deduct stock
            $pdo->prepare("UPDATE SpareParts SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$req['quantity'], $req['part_id']]);
            $pdo->prepare("UPDATE SparePartRequests SET status = 'Approved' WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid request']);
        }
    }
    elseif ($action === 'reject') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $reason = $data['reason'] ?? 'Declined by manager';
        
        $stmt = $pdo->prepare("UPDATE SparePartRequests SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'approve_all') {
        header('Content-Type: application/json; charset=utf-8');
        $reqs = $pdo->query("SELECT id, part_id, quantity FROM SparePartRequests WHERE status = 'Pending Approval'")->fetchAll();
        $count = 0;
        foreach($reqs as $r) {
            $pdo->prepare("UPDATE SpareParts SET stock_quantity = stock_quantity - ? WHERE id = ?")->execute([$r['quantity'], $r['part_id']]);
            $pdo->prepare("UPDATE SparePartRequests SET status = 'Approved' WHERE id = ?")->execute([$r['id']]);
            $count++;
        }
        echo json_encode(['success' => true, 'count' => $count]);
    }
    elseif ($action === 'reset') {
        header('Content-Type: application/json; charset=utf-8');
        $pdo->exec("TRUNCATE TABLE SparePartRequests");
        
        // Insert sample requests
        $pdo->exec("INSERT INTO SparePartRequests (job_card_id, work_order, part_id, part_name, part_number, quantity, unit_price, total_price, mechanic_name) VALUES 
            (1040, 'JC-1040', 1, 'Premium Synthetic Engine Oil (5W-30)', 'PN: LUB-5W30-SYN', 2, 450.00, 900.00, 'David Chui'),
            (1045, 'JC-1045', 2, 'Ceramic Brake Pads (Front Set)', 'PN: BRK-CER-F-09', 1, 2450.00, 2450.00, 'Sarah Jenkins'),
            (1046, 'JC-1046', 4, 'Iridium Spark Plug Set (x4)', 'PN: SPK-IRD-X4', 1, 3200.00, 3200.00, 'David Chui')");
            
        echo json_encode(['success' => true]);
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
