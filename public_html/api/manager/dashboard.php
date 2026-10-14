<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stats = [];

        // 1. Pending Bookings Count
        $stmt = $pdo->query("SELECT COUNT(*) FROM Appointments WHERE status = 'Pending'");
        $stats['pending_bookings'] = (int) $stmt->fetchColumn();

        // 2. Active Job Cards Count
        $stmt = $pdo->query("SELECT COUNT(*) FROM JobCards WHERE status NOT IN ('Completed', 'Delivered') AND (kanban_stage != 'COMPLETED' OR kanban_stage IS NULL)");
        $stats['active_job_cards'] = (int) $stmt->fetchColumn();

        // 3. Mechanics Stats
        $stmt = $pdo->query("SELECT COUNT(*) FROM Users WHERE role = 'Mechanic'");
        $stats['total_mechanics'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(DISTINCT mechanic_id) FROM JobCards WHERE mechanic_id IS NOT NULL AND status NOT IN ('Completed', 'Delivered') AND (kanban_stage != 'COMPLETED' OR kanban_stage IS NULL)");
        $stats['busy_mechanics'] = (int) $stmt->fetchColumn();

        // 4. Waiting Approvals
        $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Pending Approval'");
        $pendingParts = (int) $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM RepairEstimates WHERE status = 'Draft'");
        $pendingEstimates = (int) $stmt->fetchColumn();
        $stats['waiting_approvals'] = $pendingParts + $pendingEstimates;

        // 5. Total Revenue (Paid Invoices)
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Paid'");
        $stats['monthly_revenue'] = (float) $stmt->fetchColumn();

        // 6. Mechanics Workload List
        $stmt = $pdo->query("
            SELECT u.user_id as id, u.name, u.email, u.phone, u.specialty, u.experience, u.status, u.avatar,
                   COUNT(j.job_id) as active_jobs
            FROM Users u
            LEFT JOIN JobCards j ON u.user_id = j.mechanic_id AND j.status NOT IN ('Completed', 'Delivered') AND (j.kanban_stage != 'COMPLETED' OR j.kanban_stage IS NULL)
            WHERE u.role = 'Mechanic'
            GROUP BY u.user_id
            ORDER BY u.name ASC
        ");
        $mechs = $stmt->fetchAll();
        foreach ($mechs as &$m) {
            $m['workload'] = min(100, (int)$m['active_jobs'] * 25);
            if ($m['workload'] >= 80 && $m['status'] !== 'Off Shift') {
                $m['status'] = 'Busy';
            }
        }
        $stats['mechanics'] = $mechs;

        // 7. Recent Activities
        $stmt = $pdo->query("SELECT * FROM ActivityLogs ORDER BY created_at DESC, activity_id DESC LIMIT 10");
        $stats['recent_activities'] = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $stats]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $customerName = trim($input['customer_name'] ?? '');
        $vehicleDetails = trim($input['vehicle_details'] ?? '');
        $serviceText = trim($input['service_text'] ?? 'General Inspection');
        $estimatedCost = floatval($input['estimated_cost'] ?? 5000);
        $mechanicId = !empty($input['mechanic_id']) ? intval($input['mechanic_id']) : null;
        $appointmentId = !empty($input['appointment_id']) ? intval($input['appointment_id']) : null;

        $randNum = rand(1000, 9999);
        $code = 'JC-' . $randNum;
        $workOrder = '#WO-' . rand(2000, 8999);

        // If customer name was given and no appointment linked, find or create vehicle/owner
        if (!$appointmentId && !empty($customerName)) {
            // Find or insert User
            $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE name = ? AND role = 'VehicleOwner' LIMIT 1");
            $stmt->execute([$customerName]);
            $ownerId = $stmt->fetchColumn();

            if (!$ownerId) {
                $stmt = $pdo->prepare("INSERT INTO Users (name, email, password_hash, role, phone) VALUES (?, ?, ?, 'VehicleOwner', ?)");
                $fakeEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customerName)) . '@example.com';
                $stmt->execute([$customerName, $fakeEmail, password_hash('owner123', PASSWORD_DEFAULT), '+880 1700-000000']);
                $ownerId = $pdo->lastInsertId();
            }

            // Find or insert Vehicle
            $stmt = $pdo->prepare("SELECT vehicle_id FROM Vehicles WHERE owner_id = ? LIMIT 1");
            $stmt->execute([$ownerId]);
            $vehicleId = $stmt->fetchColumn();

            if (!$vehicleId) {
                $stmt = $pdo->prepare("INSERT INTO Vehicles (owner_id, make, model, year, license_plate, vin) VALUES (?, 'Generic', ?, 2020, ?, ?)");
                $plate = 'DHA-' . rand(10, 99) . '-' . rand(1000, 9999);
                $vin = 'VIN' . strtoupper(substr(md5(uniqid()), 0, 14));
                $stmt->execute([$ownerId, $vehicleDetails ?: 'Vehicle', $plate, $vin]);
                $vehicleId = $pdo->lastInsertId();
            }

            // Create appointment placeholder
            $stmt = $pdo->prepare("INSERT INTO Appointments (code, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, status) VALUES (?, ?, ?, 1, 1, NOW(), ?, 'Approved')");
            $stmt->execute(['BRQ-' . rand(1000, 9999), $ownerId, $vehicleId, $serviceText]);
            $appointmentId = $pdo->lastInsertId();
        }

        $stmt = $pdo->prepare("
            INSERT INTO JobCards (code, work_order, appointment_id, manager_id, mechanic_id, status, kanban_stage, progress_percentage, fault_report, service_text, estimated_cost, delivery_date)
            VALUES (?, ?, ?, 2, ?, 'Diagnosis', 'PENDING', 10, ?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 5 DAY))
        ");
        $stmt->execute([
            $code,
            $workOrder,
            $appointmentId,
            $mechanicId,
            $serviceText,
            $serviceText,
            $estimatedCost
        ]);
        $newJobId = $pdo->lastInsertId();

        // Update appointment status to Approved
        if ($appointmentId) {
            $pdo->prepare("UPDATE Appointments SET status = 'Approved' WHERE appointment_id = ?")->execute([$appointmentId]);
        }

        // Log activity
        $logText = "New Job Card <strong>{$code}</strong> ({$workOrder}) opened for {$customerName}.";
        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")->execute([$logText, $vehicleDetails]);

        echo json_encode([
            'success' => true,
            'message' => 'Job Card created successfully',
            'job_id' => $newJobId,
            'code' => $code,
            'work_order' => $workOrder
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
