<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = $pdo->query("
            SELECT j.job_id as id,
                   COALESCE(j.code, CONCAT('JC-', j.job_id)) as code,
                   COALESCE(j.work_order, CONCAT('#WO-', 2000 + j.job_id)) as work_order,
                   COALESCE(u.name, 'Customer') as customer_name,
                   CONCAT(v.make, ' ', v.model, ' • ', v.license_plate) as vehicle_details,
                   CONCAT(v.make, ' ', v.model) as vehicle_title,
                   COALESCE(v.vin, 'VIN-PENDING') as vin,
                   COALESCE(j.service_text, j.fault_report, 'General Service') as service_text,
                   COALESCE(a.priority, 'Normal') as priority,
                   j.status,
                   COALESCE(j.kanban_stage, 'PENDING') as kanban_stage,
                   COALESCE(j.progress_percentage, 10) as progress_percentage,
                   j.mechanic_id,
                   COALESCE(m.name, 'Unassigned') as mechanic_name,
                   COALESCE(
                       CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
                       'UA'
                   ) as mechanic_initials,
                   DATE_FORMAT(j.created_at, '%b %d, %Y') as date_opened,
                   COALESCE(j.delivery_date, 'TBD') as delivery_date
            FROM JobCards j
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Users u ON a.owner_id = u.user_id
            LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            ORDER BY j.job_id DESC
        ");
        $allCards = $stmt->fetchAll();

        foreach ($allCards as &$card) {
            if ($card['mechanic_name'] !== 'Unassigned') {
                $parts = explode(' ', trim($card['mechanic_name']));
                $card['mechanic_initials'] = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            } else {
                $card['mechanic_initials'] = 'UA';
            }
        }

        echo json_encode(['success' => true, 'data' => $allCards]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $cardId = intval($input['card_id'] ?? 0);
        $targetStage = trim($input['target_stage'] ?? 'PENDING');
        $newStatus = trim($input['status'] ?? '');

        if (!$cardId) {
            echo json_encode(['error' => 'Job Card ID is required']);
            exit;
        }

        $progressPercentage = 25;
        if ($targetStage === 'COMPLETED') {
            $newStatus = 'Ready';
            $progressPercentage = 100;
        } elseif ($targetStage === 'IN PROGRESS') {
            if (empty($newStatus) || $newStatus === 'Diagnosis') $newStatus = 'Repairing';
            $progressPercentage = 65;
        } else {
            $targetStage = 'PENDING';
            if (empty($newStatus)) $newStatus = 'Diagnosis';
            $progressPercentage = 20;
        }

        $stmt = $pdo->prepare("
            UPDATE JobCards 
            SET kanban_stage = ?, status = ?, progress_percentage = ?
            WHERE job_id = ?
        ");
        $stmt->execute([$targetStage, $newStatus, $progressPercentage, $cardId]);

        // Insert into timeline
        $pdo->prepare("INSERT INTO RepairTimeline (job_id, stage, updated_by) VALUES (?, ?, 2)")
            ->execute([$cardId, $newStatus]);

        // Get job card code
        $stmt = $pdo->prepare("SELECT code, work_order FROM JobCards WHERE job_id = ?");
        $stmt->execute([$cardId]);
        $card = $stmt->fetch();
        $code = $card['code'] ?? ('JC-' . $cardId);

        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
            ->execute(["Job Card <strong>{$code}</strong> updated to '{$newStatus}'.", $targetStage]);

        echo json_encode([
            'success' => true,
            'message' => "Job Card moved to {$targetStage}",
            'kanban_stage' => $targetStage,
            'status' => $newStatus,
            'progress_percentage' => $progressPercentage
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
