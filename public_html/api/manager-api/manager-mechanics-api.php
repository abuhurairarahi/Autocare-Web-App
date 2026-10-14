<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    // Add required columns to real users table if they don't exist
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN specialty VARCHAR(100) DEFAULT 'General', ADD COLUMN experience INT DEFAULT 5, ADD COLUMN avatar VARCHAR(255)");
    } catch (Exception $e) {}

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $mechanics = $pdo->query("SELECT * FROM users WHERE role = 'Mechanic'")->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate workload dynamically based on active job cards
        foreach ($mechanics as &$m) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobcards WHERE mechanic_id = ? AND status NOT IN ('Delivered', 'Ready', 'Completed')");
            $stmt->execute([$m['id']]);
            $activeJobs = (int)$stmt->fetchColumn();
            
            $workloadPct = min(100, $activeJobs * 25);
            $m['workload'] = $workloadPct;
            $m['experience'] = $m['experience'] ? $m['experience'] . ' Years' : '5 Years';
            
            if ($m['status'] !== 'Inactive') {
                if ($workloadPct >= 80) {
                    $m['status'] = 'Busy';
                } elseif ($workloadPct === 0) {
                    $m['status'] = 'Available';
                }
                // We shouldn't auto-update the 'status' column in 'users' here if it represents account status (Active/Inactive),
                // but since the frontend expects 'Busy' / 'Available', we just pass it in the JSON response.
            } else {
                $m['status'] = 'Off Shift';
            }
        }
        
        echo json_encode(['success' => true, 'mechanics' => $mechanics]);
    }
    elseif ($action === 'active_jobs') {
        header('Content-Type: application/json; charset=utf-8');
        $jobs = $pdo->query("
            SELECT j.id, 
                   CONCAT('JC-', YEAR(j.created_at), '-', j.id) AS code, 
                   u_cust.name AS customer_name, 
                   CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') AS vehicle_details 
            FROM jobcards j
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON a.owner_id = u_cust.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            WHERE j.status NOT IN ('Delivered', 'Ready', 'Completed') 
            ORDER BY j.id DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'jobs' => $jobs]);
    }
    elseif ($action === 'assign_job') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $jobId = (int)($data['job_id'] ?? 0);
        $mechId = (int)($data['mechanic_id'] ?? 0);

        if ($jobId > 0 && $mechId > 0) {
            $stmt = $pdo->prepare("UPDATE jobcards SET mechanic_id = ? WHERE id = ?");
            $stmt->execute([$mechId, $jobId]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
