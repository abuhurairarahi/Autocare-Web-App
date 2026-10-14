<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $status = $_GET['status'] ?? null;
        $priority = $_GET['priority'] ?? null;

        $sql = "
            SELECT a.appointment_id as id,
                   COALESCE(a.code, CONCAT('BRQ-2026-', LPAD(a.appointment_id, 3, '0'))) as code,
                   a.owner_id,
                   u.name as customer_name,
                   u.phone as customer_phone,
                   u.email as customer_email,
                   a.vehicle_id,
                   v.make,
                   v.model as vehicle_model,
                   v.license_plate,
                   v.vin,
                   a.service_category_id as category_id,
                   sc.name as category_name,
                   sc.description as category_description,
                   sc.base_rate,
                   a.preferred_date,
                   DATE_FORMAT(a.preferred_date, '%b %d, %H:%i') as formatted_date,
                   a.issue_description as description,
                   COALESCE(a.priority, 'Normal') as priority,
                   a.status,
                   a.created_at
            FROM Appointments a
            JOIN Users u ON a.owner_id = u.user_id
            JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.category_id
            WHERE 1=1
        ";

        $params = [];
        if ($status && $status !== 'all') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        if ($priority && $priority !== 'all') {
            $sql .= " AND a.priority = ?";
            $params[] = $priority;
        }

        $sql .= " ORDER BY a.appointment_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $requests]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? 'approve';
        $appointmentId = intval($input['appointment_id'] ?? 0);

        if (!$appointmentId) {
            echo json_encode(['error' => 'Appointment ID is required']);
            exit;
        }

        if ($action === 'approve') {
            $mechanicId = !empty($input['mechanic_id']) ? intval($input['mechanic_id']) : null;
            $estimatedCost = floatval($input['estimated_cost'] ?? 5000);
            $serviceScope = trim($input['service_scope'] ?? '');

            // Update appointment status to Approved
            $stmt = $pdo->prepare("UPDATE Appointments SET status = 'Approved' WHERE appointment_id = ?");
            $stmt->execute([$appointmentId]);

            // Check if job card already exists for this appointment
            $stmt = $pdo->prepare("SELECT job_id, code FROM JobCards WHERE appointment_id = ?");
            $stmt->execute([$appointmentId]);
            $existing = $stmt->fetch();

            if ($existing) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment already has Job Card ' . $existing['code'],
                    'job_id' => $existing['job_id'],
                    'code' => $existing['code']
                ]);
                exit;
            }

            // Create new Job Card
            $code = 'JC-' . rand(1000, 9999);
            $workOrder = '#WO-' . rand(2000, 8999);

            $stmt = $pdo->prepare("
                INSERT INTO JobCards (code, work_order, appointment_id, manager_id, mechanic_id, status, kanban_stage, progress_percentage, fault_report, service_text, estimated_cost, delivery_date)
                VALUES (?, ?, ?, 2, ?, 'Diagnosis', 'PENDING', 10, ?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 4 DAY))
            ");
            $stmt->execute([
                $code,
                $workOrder,
                $appointmentId,
                $mechanicId,
                $serviceScope,
                $serviceScope,
                $estimatedCost
            ]);
            $jobId = $pdo->lastInsertId();

            // Log activity
            $logText = "Booking Request approved. Job Card <strong>{$code}</strong> opened.";
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                ->execute([$logText, $workOrder]);

            echo json_encode([
                'success' => true,
                'message' => 'Booking request approved & Job Card created',
                'job_id' => $jobId,
                'code' => $code,
                'work_order' => $workOrder
            ]);
        }
        elseif ($action === 'reject') {
            $reason = trim($input['reason'] ?? 'Customer request cancelled or slot unavailable');

            $stmt = $pdo->prepare("UPDATE Appointments SET status = 'Rejected' WHERE appointment_id = ?");
            $stmt->execute([$appointmentId]);

            // Log activity
            $logText = "Booking Request #{$appointmentId} rejected: {$reason}";
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'red', 'Rejected')")
                ->execute([$logText]);

            echo json_encode([
                'success' => true,
                'message' => 'Booking request rejected'
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
