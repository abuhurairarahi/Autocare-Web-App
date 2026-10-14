<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $query = "
            SELECT j.id, 
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS code, 
                   j.status, 
                   j.mechanic_id, 
                   u_mech.name AS mechanic_name,
                   u_cust.name AS customer_name,
                   CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') AS vehicle_details,
                   j.estimated_cost, 
                   j.fault_report AS description, 
                   DATE_FORMAT(j.created_at, '%b %d, %Y') AS created_at,
                   j.created_at AS date_opened,
                   j.progress_percentage
            FROM jobcards j
            LEFT JOIN users u_mech ON j.mechanic_id = u_mech.id
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            ORDER BY j.id DESC
        ";
        $cards = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        // Let's populate the missing 'code' or map it
        
        $partsCount = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE status = 'Testing'")->fetchColumn(); // Fallback for awaiting parts
        $totalActive = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE status NOT IN ('Delivered', 'Ready', 'Completed')")->fetchColumn();
        $qualityControl = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE status IN ('Testing')")->fetchColumn();
        $completedToday = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE status IN ('Ready', 'Delivered', 'Completed') AND DATE(created_at) = CURRENT_DATE()")->fetchColumn();

        echo json_encode([
            'success' => true, 
            'cards' => $cards,
            'stats' => [
                'total_active' => $totalActive,
                'awaiting_parts' => $partsCount,
                'quality_control' => $qualityControl,
                'completed_today' => $completedToday
            ]
        ]);
    }
    elseif ($action === 'mechanics') {
        header('Content-Type: application/json; charset=utf-8');
        $mechanics = $pdo->query("SELECT id, name, role, status FROM users WHERE role = 'Mechanic'")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'mechanics' => $mechanics]);
    }
    elseif ($action === 'create_job_card') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $custName = trim($data['customer_name'] ?? 'Unknown Customer');
        $vehicleInfo = trim($data['vehicle_details'] ?? 'Unknown Vehicle');
        $mechId = !empty($data['mechanic_id']) ? (int)$data['mechanic_id'] : null;
        $estCost = !empty($data['estimated_cost']) ? (float)$data['estimated_cost'] : 0.0;
        $desc = trim($data['service_text'] ?? '');
        
        if (empty($custName) || empty($vehicleInfo)) {
            echo json_encode(['success' => false, 'error' => 'Customer name and vehicle are required.']);
            exit;
        }

        $pdo->beginTransaction();

        // 1. Find or Create User
        $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'VehicleOwner' LIMIT 1");
        $stmt->execute([$custName]);
        $custId = $stmt->fetchColumn();
        if (!$custId) {
            $stmt = $pdo->prepare("INSERT INTO users (name, role, email) VALUES (?, 'VehicleOwner', ?)");
            $stmt->execute([$custName, 'customer_' . time() . '@example.com']);
            $custId = $pdo->lastInsertId();
        }

        // 2. Find or Create Vehicle
        $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE owner_id = ? LIMIT 1");
        $stmt->execute([$custId]);
        $vehId = $stmt->fetchColumn();
        if (!$vehId) {
            $stmt = $pdo->prepare("INSERT INTO vehicles (owner_id, make, model, license_plate) VALUES (?, 'Unknown', ?, ?)");
            $stmt->execute([$custId, substr($vehicleInfo, 0, 50), 'NA-'.rand(100,999)]);
            $vehId = $pdo->lastInsertId();
        }
        
        // 3. Create Appointment
        $stmt = $pdo->prepare("INSERT INTO appointments (owner_id, vehicle_id, preferred_date, issue_description, status) VALUES (?, ?, CURRENT_DATE(), ?, 'Approved')");
        $stmt->execute([$custId, $vehId, $desc]);
        $appId = $pdo->lastInsertId();

        // 4. Create Job Card
        $stmt = $pdo->prepare("INSERT INTO jobcards (appointment_id, mechanic_id, status, priority, estimated_cost) VALUES (?, ?, 'In Progress', 'Standard', ?)");
        $stmt->execute([$appId, $mechId, $estCost]);
        $jobId = $pdo->lastInsertId();
        
        $code = 'JC-' . date('Y') . '-' . $jobId;
        
        $pdo->commit();
        echo json_encode(['success' => true, 'code' => $code]);
    }
    elseif ($action === 'details') {
        header('Content-Type: application/json; charset=utf-8');
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("
            SELECT j.id, 
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS code, 
                   j.status, 
                   j.mechanic_id, 
                   u_mech.name AS mechanic_name,
                   u_cust.name AS customer_name,
                   CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') AS vehicle_details,
                   j.estimated_cost, 
                   j.fault_report AS description, 
                   DATE_FORMAT(j.created_at, '%b %d, %Y') AS created_at,
                   j.progress_percentage
            FROM jobcards j
            LEFT JOIN users u_mech ON j.mechanic_id = u_mech.id
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            WHERE j.id = ?
        ");
        $stmt->execute([$id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($card) {
            echo json_encode(['success' => true, 'card' => $card]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Job card not found']);
        }
    }
    elseif ($action === 'update_stage') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $stage = $data['stage'] ?? '';
        
        $validStages = ['Diagnosis', 'Repairing', 'In Progress', 'Testing', 'Quality Control', 'Ready', 'Completed'];
        if (!in_array($stage, $validStages)) {
            echo json_encode(['success' => false, 'error' => 'Invalid stage']);
            exit;
        }
        
        // Map UI stages to DB stages
        if ($stage === 'Quality Control') $stage = 'Testing';
        if ($stage === 'Repairing') $stage = 'In Progress';
        
        $stmt = $pdo->prepare("UPDATE jobcards SET status = ? WHERE id = ?");
        $stmt->execute([$stage, $id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'generate_invoice') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['job_card_id'] ?? 0);
        
        $stmt = $pdo->prepare("SELECT j.estimated_cost, a.owner_id FROM jobcards j JOIN appointments a ON j.appointment_id = a.id WHERE j.id = ?");
        $stmt->execute([$id]);
        $card = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($card) {
            $cost = (float)$card['estimated_cost'];
            $invNumber = 'INV-' . date('Ymd') . '-' . rand(100, 999); // Mock invoice number as we use ID in DB
            
            $stmtInv = $pdo->prepare("INSERT INTO invoices (job_card_id, customer_id, total_amount, status) VALUES (?, ?, ?, 'Unpaid')");
            $stmtInv->execute([$id, $card['owner_id'], $cost]);
            echo json_encode(['success' => true, 'invoice_number' => $invNumber]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Job card not found']);
        }
    }
    elseif ($action === 'reassign_mechanic') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $mechId = !empty($data['mechanic_id']) ? (int)$data['mechanic_id'] : null;

        $stmt = $pdo->prepare("UPDATE jobcards SET mechanic_id = ? WHERE id = ?");
        $stmt->execute([$mechId, $id]);
        echo json_encode(['success' => true]);
    }
    elseif ($action === 'update_kanban') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $stage = $data['kanban_stage'] ?? '';
        $progress = (int)($data['progress_percentage'] ?? 0);

        if ($id > 0) {
            $status = 'In Progress';
            if ($stage === 'PENDING') $status = 'Diagnosis';
            elseif ($stage === 'COMPLETED') $status = 'Completed';
            elseif ($stage === 'IN PROGRESS' && $progress >= 85) $status = 'Testing';

            $stmt = $pdo->prepare("UPDATE jobcards SET status = ?, progress_percentage = ? WHERE id = ?");
            $stmt->execute([$status, $progress, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
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
