<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $filter = $_GET['filter'] ?? 'all';
        $jobId = $_GET['id'] ?? null;

        $sql = "
            SELECT j.job_id as id,
                   COALESCE(j.code, CONCAT('JC-', j.job_id)) as code,
                   COALESCE(j.work_order, CONCAT('#WO-', 2000 + j.job_id)) as work_order,
                   j.appointment_id,
                   COALESCE(u.name, 'Valued Customer') as customer_name,
                   u.phone as customer_phone,
                   u.email as customer_email,
                   CONCAT(v.make, ' ', v.model, ' • ', v.license_plate) as vehicle_details,
                   CONCAT(v.make, ' ', v.model) as vehicle_title,
                   COALESCE(v.vin, 'VIN-PENDING') as vin,
                   j.mechanic_id,
                   COALESCE(m.name, 'Unassigned') as mechanic_name,
                   COALESCE(
                       CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
                       'UA'
                   ) as mechanic_initials,
                   COALESCE(a.priority, 'Normal') as priority,
                   j.status,
                   COALESCE(j.kanban_stage, 'PENDING') as kanban_stage,
                   COALESCE(j.progress_percentage, 10) as progress_percentage,
                   j.estimated_cost,
                   DATE_FORMAT(j.created_at, '%b %d, %Y') as date_opened,
                   COALESCE(j.delivery_date, DATE_FORMAT(DATE_ADD(j.created_at, INTERVAL 4 DAY), '%b %d, %Y')) as delivery_date,
                   COALESCE(j.service_text, j.fault_report, 'General Service') as service_text,
                   j.fault_report
            FROM JobCards j
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Users u ON a.owner_id = u.user_id
            LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            WHERE 1=1
        ";

        $params = [];
        if ($jobId) {
            $sql .= " AND j.job_id = ?";
            $params[] = intval($jobId);
        }

        if ($filter === 'In-Progress') {
            $sql .= " AND (j.status = 'Repairing' OR j.kanban_stage = 'IN PROGRESS')";
        } elseif ($filter === 'Awaiting-Parts') {
            $sql .= " AND (j.status = 'Awaiting Parts' OR j.progress_percentage < 40)";
        } elseif ($filter === 'Quality-Control') {
            $sql .= " AND (j.status = 'Testing' OR j.progress_percentage >= 85)";
        }

        $sql .= " ORDER BY j.job_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();

        // Calculate initials accurately
        foreach ($jobs as &$job) {
            if ($job['mechanic_name'] !== 'Unassigned') {
                $parts = explode(' ', trim($job['mechanic_name']));
                $initials = '';
                foreach ($parts as $p) {
                    if (!empty($p)) $initials .= strtoupper($p[0]);
                }
                $job['mechanic_initials'] = substr($initials, 0, 2);
            } else {
                $job['mechanic_initials'] = 'UA';
            }
        }

        echo json_encode(['success' => true, 'data' => $jobId && count($jobs) > 0 ? $jobs[0] : $jobs]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $customerName = trim($input['customer_name'] ?? 'Customer');
            $vehicleDetails = trim($input['vehicle_details'] ?? 'Vehicle');
            $serviceText = trim($input['service_text'] ?? 'Standard Repair');
            $estimatedCost = floatval($input['estimated_cost'] ?? 5000);
            $mechanicId = !empty($input['mechanic_id']) ? intval($input['mechanic_id']) : null;
            $deliveryDate = $input['delivery_date'] ?? date('M d, Y', strtotime('+4 days'));

            $code = 'JC-' . rand(1000, 9999);
            $workOrder = '#WO-' . rand(2000, 8999);

            // Find or insert User
            $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE name = ? AND role = 'VehicleOwner' LIMIT 1");
            $stmt->execute([$customerName]);
            $ownerId = $stmt->fetchColumn();

            if (!$ownerId) {
                $stmt = $pdo->prepare("INSERT INTO Users (name, email, password_hash, role, phone) VALUES (?, ?, ?, 'VehicleOwner', ?)");
                $fakeEmail = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customerName)) . rand(10,99) . '@example.com';
                $stmt->execute([$customerName, $fakeEmail, password_hash('owner123', PASSWORD_DEFAULT), '+880 1700-000000']);
                $ownerId = $pdo->lastInsertId();
            }

            // Find or insert Vehicle
            $stmt = $pdo->prepare("SELECT vehicle_id FROM Vehicles WHERE owner_id = ? LIMIT 1");
            $stmt->execute([$ownerId]);
            $vehicleId = $stmt->fetchColumn();

            if (!$vehicleId) {
                $stmt = $pdo->prepare("INSERT INTO Vehicles (owner_id, make, model, year, license_plate, vin) VALUES (?, 'Auto', ?, 2021, ?, ?)");
                $plate = 'DHA-' . rand(10, 99) . '-' . rand(1000, 9999);
                $vin = 'VIN' . strtoupper(substr(md5(uniqid()), 0, 14));
                $stmt->execute([$ownerId, $vehicleDetails, $plate, $vin]);
                $vehicleId = $pdo->lastInsertId();
            }

            // Create linked appointment
            $stmt = $pdo->prepare("INSERT INTO Appointments (code, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, status) VALUES (?, ?, ?, 1, 1, NOW(), ?, 'Approved')");
            $stmt->execute(['BRQ-' . rand(1000, 9999), $ownerId, $vehicleId, $serviceText]);
            $appointmentId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO JobCards (code, work_order, appointment_id, manager_id, mechanic_id, status, kanban_stage, progress_percentage, fault_report, service_text, estimated_cost, delivery_date)
                VALUES (?, ?, ?, 2, ?, 'Diagnosis', 'PENDING', 15, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $code,
                $workOrder,
                $appointmentId,
                $mechanicId,
                $serviceText,
                $serviceText,
                $estimatedCost,
                $deliveryDate
            ]);
            $jobId = $pdo->lastInsertId();

            // Log
            $logText = "New Job Card <strong>{$code}</strong> ({$workOrder}) opened for {$customerName}.";
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                ->execute([$logText, $vehicleDetails]);

            echo json_encode([
                'success' => true,
                'message' => 'Job card created successfully',
                'job_id' => $jobId,
                'code' => $code,
                'work_order' => $workOrder
            ]);
        }
        elseif ($action === 'update_status') {
            $jobId = intval($input['job_id'] ?? 0);
            $status = trim($input['status'] ?? '');
            $kanbanStage = trim($input['kanban_stage'] ?? '');
            $progressPct = isset($input['progress_percentage']) ? intval($input['progress_percentage']) : null;

            if (!$jobId) {
                echo json_encode(['error' => 'Job ID required']);
                exit;
            }

            if ($kanbanStage === 'COMPLETED' || $status === 'Ready' || $status === 'Completed') {
                $progressPct = 100;
                $kanbanStage = 'COMPLETED';
                $status = 'Completed';
            } elseif ($kanbanStage === 'IN PROGRESS' || $status === 'Repairing' || $status === 'Testing') {
                if (!$progressPct || $progressPct < 40) $progressPct = 65;
                $kanbanStage = 'IN PROGRESS';
            } elseif ($kanbanStage === 'PENDING' || $status === 'Diagnosis') {
                if (!$progressPct) $progressPct = 25;
                $kanbanStage = 'PENDING';
            }

            $stmt = $pdo->prepare("
                UPDATE JobCards 
                SET status = ?, kanban_stage = ?, progress_percentage = COALESCE(?, progress_percentage)
                WHERE job_id = ?
            ");
            $stmt->execute([$status, $kanbanStage, $progressPct, $jobId]);

            // Add timeline entry
            $pdo->prepare("INSERT INTO RepairTimeline (job_id, stage, updated_by) VALUES (?, ?, 2)")
                ->execute([$jobId, $status ?: $kanbanStage]);

            // Log
            $stmt = $pdo->prepare("SELECT code FROM JobCards WHERE job_id = ?");
            $stmt->execute([$jobId]);
            $code = $stmt->fetchColumn() ?: ('JC-' . $jobId);

            $logText = "Job Card <strong>{$code}</strong> moved to '{$status}'.";
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                ->execute([$logText, $kanbanStage]);

            echo json_encode(['success' => true, 'message' => 'Job Card status updated successfully']);
        }
        elseif ($action === 'assign_mechanic') {
            $jobId = intval($input['job_id'] ?? 0);
            $mechanicId = intval($input['mechanic_id'] ?? 0);

            if (!$jobId || !$mechanicId) {
                echo json_encode(['error' => 'Job ID and Mechanic ID required']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE JobCards SET mechanic_id = ? WHERE job_id = ?");
            $stmt->execute([$mechanicId, $jobId]);

            // Get names for log
            $stmt = $pdo->prepare("SELECT name FROM Users WHERE user_id = ?");
            $stmt->execute([$mechanicId]);
            $mechName = $stmt->fetchColumn() ?: 'Mechanic';

            $stmt = $pdo->prepare("SELECT code FROM JobCards WHERE job_id = ?");
            $stmt->execute([$jobId]);
            $code = $stmt->fetchColumn() ?: ('JC-' . $jobId);

            $logText = "Job Card <strong>{$code}</strong> assigned to {$mechName}.";
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', 'Assigned')")
                ->execute([$logText]);

            echo json_encode(['success' => true, 'message' => "Job Card assigned to {$mechName}"]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
