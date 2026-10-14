<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();

    $action = $_GET['action'] ?? ($_POST['action'] ?? 'dashboard');

    if ($action === 'dashboard') {
        header('Content-Type: application/json; charset=utf-8');
        
        $pendingBookings = (int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
        $activeJobCards = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE status NOT IN ('Completed', 'Delivered')")->fetchColumn();
        $assignedMechanics = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'Mechanic'")->fetchColumn(); 
        $waitingApproval = (int)$pdo->query("SELECT COUNT(*) FROM managerapprovals WHERE status = 'Pending'")->fetchColumn();
        
        try {
            $monthlyRevenue = (float)$pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status = 'Paid' AND MONTH(issued_date) = MONTH(CURRENT_DATE()) AND YEAR(issued_date) = YEAR(CURRENT_DATE())")->fetchColumn();
        } catch (Exception $e) {
            $monthlyRevenue = 0;
        }

        // Workload
        $mechanics = [];
        $mechRows = $pdo->query("SELECT id, name FROM users WHERE role = 'Mechanic'")->fetchAll();
        foreach ($mechRows as $i => $m) {
            try {
                $jobs = (int)$pdo->query("SELECT COUNT(*) FROM jobcards WHERE mechanic_id = {$m['id']} AND status NOT IN ('Completed', 'Delivered')")->fetchColumn();
            } catch (Exception $e) {
                $jobs = 0;
            }
            $mechanics[] = [
                'id' => $m['id'],
                'name' => $m['name'],
                'jobs' => $jobs,
                'workload_pct' => min(100, $jobs * 20),
                'specialty' => 'General',
                'status' => $jobs > 0 ? 'Busy' : 'Available'
            ];
        }

        // Pending Requests for Dropdown
        $pendingRequestsList = [];
        try {
            $reqs = $pdo->query("
                SELECT a.id, CONCAT('REQ-', a.id) AS code, a.issue_description AS description, 
                       u.name AS customer_name, CONCAT(v.make, ' ', v.model, ' (', v.license_plate, ')') AS vehicle_details
                FROM appointments a
                LEFT JOIN users u ON a.owner_id = u.id
                LEFT JOIN vehicles v ON a.vehicle_id = v.id
                WHERE a.status = 'Pending'
                ORDER BY a.id DESC LIMIT 50
            ")->fetchAll();
            $pendingRequestsList = $reqs;
        } catch (Exception $e) {}

        // Recent Activity
        $activities = [];
        try {
            $acts = $pdo->query("SELECT text, subtext, type, link FROM manageractivities ORDER BY id DESC LIMIT 10")->fetchAll();
            $activities = $acts;
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'stats' => [
                'pending_bookings' => $pendingBookings,
                'active_jobs' => $activeJobCards,
                'mechanics_assigned' => $assignedMechanics,
                'mechanics_total' => $assignedMechanics,
                'waiting_approval' => $waitingApproval,
                'monthly_revenue' => $monthlyRevenue
            ],
            'mechanics' => $mechanics,
            'activities' => $activities,
            'pending_requests' => $pendingRequestsList
        ]);
    }
    elseif ($action === 'trend') {
        header('Content-Type: application/json; charset=utf-8');
        $period = $_GET['period'] ?? 'monthly';
        
        $labels = [];
        $dataPoints = [];
        
        if ($period === 'monthly') {
            try {
                $query = "SELECT DATE(issued_date) as date, SUM(total_amount) as total 
                          FROM invoices 
                          WHERE status = 'Paid' AND MONTH(issued_date) = MONTH(CURRENT_DATE()) AND YEAR(issued_date) = YEAR(CURRENT_DATE())
                          GROUP BY DATE(issued_date) ORDER BY date";
                $rows = $pdo->query($query)->fetchAll();
                
                $daysInMonth = (int)date('t');
                $currentMonth = date('Y-m');
                $map = [];
                foreach ($rows as $r) {
                    $map[$r['date']] = (float)$r['total'];
                }
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $dateStr = $currentMonth . '-' . str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                    $labels[] = $i;
                    $dataPoints[] = $map[$dateStr] ?? 0;
                }
            } catch (Exception $e) {
                // Return zeros instead of mock data
                $daysInMonth = (int)date('t');
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $labels[] = $i;
                    $dataPoints[] = 0;
                }
            }
        } else {
            try {
                $query = "SELECT MONTH(issued_date) as month, SUM(total_amount) as total 
                          FROM invoices 
                          WHERE status = 'Paid' AND YEAR(issued_date) = YEAR(CURRENT_DATE())
                          GROUP BY MONTH(issued_date) ORDER BY month";
                $rows = $pdo->query($query)->fetchAll();
                
                $map = [];
                foreach ($rows as $r) {
                    $map[(int)$r['month']] = (float)$r['total'];
                }
                
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                for ($i = 1; $i <= 12; $i++) {
                    $labels[] = $monthNames[$i - 1];
                    $dataPoints[] = $map[$i] ?? 0;
                }
            } catch (Exception $e) {
                $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                for ($i = 1; $i <= 12; $i++) {
                    $labels[] = $monthNames[$i - 1];
                    $dataPoints[] = 0;
                }
            }
        }
        
        echo json_encode(['success' => true, 'points' => $dataPoints, 'labels' => $labels]);
    }
    elseif ($action === 'create_job_card') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $custName = trim($data['customer_name'] ?? 'Unknown Customer');
        $vehicleInfo = trim($data['vehicle_details'] ?? 'Unknown Vehicle');
        $mechId = !empty($data['mechanic_id']) ? (int)$data['mechanic_id'] : null;
        $desc = trim($data['service_text'] ?? '');
        
        // Find or create customer
        $stmt = $pdo->prepare("SELECT id FROM users WHERE name = ? AND role = 'Customer' LIMIT 1");
        $stmt->execute([$custName]);
        $custId = $stmt->fetchColumn();
        if (!$custId) {
            $stmt = $pdo->prepare("INSERT INTO users (name, role, email) VALUES (?, 'Customer', ?)");
            $stmt->execute([$custName, 'customer_' . time() . '@example.com']);
            $custId = $pdo->lastInsertId();
        }

        // Find or create vehicle
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
        $jobcardId = $pdo->lastInsertId();
        
        $code = 'JC-' . date('Y') . '-' . $jobcardId;
        
        $stmtAct = $pdo->prepare("INSERT INTO manageractivities (text, subtext, type, link) VALUES (?, ?, 'blue', 'manager-jobCards.html')");
        $stmtAct->execute(["New Job Card $code created for $custName", "Just now • $vehicleInfo"]);

        echo json_encode(['success' => true, 'code' => $code]);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
