<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $filter = $_GET['priority'] ?? 'all';
        $sort = $_GET['sort'] ?? 'date';
        
        $query = "SELECT a.id, CONCAT('BRQ-', YEAR(a.created_at), '-', a.id) AS code, a.issue_description AS description, 
                         u.name AS customer_name, u.phone AS phone, 
                         v.make AS vehicle_make, v.model AS vehicle_model, v.year AS vehicle_year, 
                         v.vin AS vehicle_vin, v.license_plate AS vehicle_plate, 
                         'Normal' AS priority, a.status, 
                         DATE_FORMAT(a.created_at, '%b %d, %H:%i') AS created_at
                  FROM appointments a
                  LEFT JOIN users u ON a.owner_id = u.id
                  LEFT JOIN vehicles v ON a.vehicle_id = v.id
                  WHERE a.status = 'Pending'";
                  
        // priority filter not really applicable since appointments don't have priority, but we ignore it for now
        
        if ($sort === 'priority') {
            $query .= " ORDER BY a.id DESC";
        } else {
            $query .= " ORDER BY a.id DESC";
        }
        
        $requests = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'requests' => $requests]);
    }
    elseif ($action === 'mechanics') {
        header('Content-Type: application/json; charset=utf-8');
        $mechanics = $pdo->query("SELECT id, name, role, status FROM users WHERE role = 'Mechanic'")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'mechanics' => $mechanics]);
    }
    elseif ($action === 'approve') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $reqId = $data['request_id'] ?? 0;
        $mechId = !empty($data['mechanic_id']) ? (int)$data['mechanic_id'] : null;
        
        $code = 'JC-' . rand(1000, 9999);
        
        $pdo->beginTransaction();
        
        // Update request
        $stmtUpdate = $pdo->prepare("UPDATE appointments SET status = 'Approved' WHERE id = ?");
        $stmtUpdate->execute([$reqId]);
        
        // Insert job card
        $stmtInsert = $pdo->prepare("INSERT INTO jobcards (appointment_id, mechanic_id, status, priority) VALUES (?, ?, 'Assigned', 'Standard')");
        $stmtInsert->execute([$reqId, $mechId]);
        
        $jobId = $pdo->lastInsertId();
        
        // Log manager activity
        $stmtAct = $pdo->prepare("INSERT INTO manageractivities (text, subtext, type, link) VALUES (?, ?, 'blue', 'manager-jobCards.html')");
        $stmtAct->execute(["Approved Booking #{$reqId} and created Job Card", "Assigned Mechanic ID: {$mechId}"]);

        $pdo->commit();
        
        echo json_encode(['success' => true, 'code' => $code]);
    }
    elseif ($action === 'reject') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $reqId = $data['request_id'] ?? 0;
        $reason = $data['reason'] ?? '';
        
        $stmtUpdate = $pdo->prepare("UPDATE appointments SET status = 'Rejected' WHERE id = ?");
        $stmtUpdate->execute([$reqId]);
        
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'history') {
        header('Content-Type: application/json; charset=utf-8');
        $plate = $_GET['plate'] ?? '';
        
        // Fetch real history
        $stmt = $pdo->prepare("SELECT j.id, CONCAT('JC-', j.id) as code, 'General Service' as title, j.status, 
                                      DATE_FORMAT(j.created_at, '%b %d, %Y') as date, 
                                      COALESCE(j.fault_report, 'Standard maintenance performed.') as details
                               FROM jobcards j
                               JOIN appointments a ON j.appointment_id = a.id
                               JOIN vehicles v ON a.vehicle_id = v.id
                               WHERE v.license_plate = ? AND j.status IN ('Completed', 'Delivered')
                               ORDER BY j.id DESC LIMIT 5");
        $stmt->execute([$plate]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'history' => $history]);
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
