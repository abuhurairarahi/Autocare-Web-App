<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $specialty = $_GET['specialty'] ?? null;
        $status = $_GET['status'] ?? null;

        $sql = "
            SELECT u.user_id as id,
                   u.name,
                   u.email,
                   u.phone,
                   COALESCE(u.specialty, 'General Repair') as specialty,
                   COALESCE(u.experience, '5 Years') as experience,
                   COALESCE(u.status, 'Available') as status,
                   COALESCE(u.avatar, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150') as avatar,
                   COUNT(j.job_id) as active_jobs
            FROM Users u
            LEFT JOIN JobCards j ON u.user_id = j.mechanic_id AND j.status NOT IN ('Completed', 'Delivered') AND (j.kanban_stage != 'COMPLETED' OR j.kanban_stage IS NULL)
            WHERE u.role = 'Mechanic'
        ";

        $params = [];
        if ($specialty && $specialty !== 'all') {
            $sql .= " AND LOWER(u.specialty) LIKE ?";
            $params[] = '%' . strtolower($specialty) . '%';
        }
        if ($status && $status !== 'all') {
            $sql .= " AND LOWER(u.status) = ?";
            $params[] = strtolower($status);
        }

        $sql .= " GROUP BY u.user_id ORDER BY u.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $mechanics = $stmt->fetchAll();

        foreach ($mechanics as &$m) {
            $workload = min(100, (int)$m['active_jobs'] * 25);
            $m['workload'] = $workload;
            if ($workload >= 80 && $m['status'] !== 'Off Shift') {
                $m['status'] = 'Busy';
            }
        }

        echo json_encode(['success' => true, 'data' => $mechanics]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? 'assign';

        if ($action === 'assign') {
            $mechanicId = intval($input['mechanic_id'] ?? 0);
            $jobId = intval($input['job_id'] ?? 0);

            if (!$mechanicId || !$jobId) {
                echo json_encode(['error' => 'Mechanic ID and Job ID required']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE JobCards SET mechanic_id = ? WHERE job_id = ?");
            $stmt->execute([$mechanicId, $jobId]);

            // Fetch details for response
            $stmt = $pdo->prepare("SELECT name FROM Users WHERE user_id = ?");
            $stmt->execute([$mechanicId]);
            $mechName = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT code FROM JobCards WHERE job_id = ?");
            $stmt->execute([$jobId]);
            $code = $stmt->fetchColumn();

            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', 'Reassigned')")
                ->execute(["Job Card <strong>{$code}</strong> assigned to {$mechName}.", 'Mechanic']);

            echo json_encode(['success' => true, 'message' => "Job Card assigned to {$mechName}"]);
        }
        elseif ($action === 'update_status') {
            $mechanicId = intval($input['mechanic_id'] ?? 0);
            $newStatus = trim($input['status'] ?? 'Available');

            $stmt = $pdo->prepare("UPDATE Users SET status = ? WHERE user_id = ? AND role = 'Mechanic'");
            $stmt->execute([$newStatus, $mechanicId]);

            echo json_encode(['success' => true, 'message' => "Mechanic status updated to {$newStatus}"]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
