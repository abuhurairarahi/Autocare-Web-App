<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../db.php';

try {
    $pdo = databaseConnection();
    
    // Ensure the customer_email column exists (from previous mock schema changes if any)
    try {
        $pdo->exec("ALTER TABLE invoices ADD COLUMN customer_email VARCHAR(100)");
    } catch(Exception $e) {}

    $action = $_GET['action'] ?? ($_POST['action'] ?? '');

    if ($action === 'list') {
        header('Content-Type: application/json; charset=utf-8');
        
        $query = "
            SELECT i.id, 
                   CONCAT('INV-', YEAR(i.issued_date), '-', LPAD(i.id, 4, '0')) AS invoice_number,
                   i.total_amount, 
                   i.status,
                   i.customer_email,
                   DATE_FORMAT(i.issued_date, '%b %d, %Y') AS date,
                   u_cust.name AS customer_name,
                   CONCAT(v.make, ' ', v.model) AS vehicle_name,
                   v.vin AS vin
            FROM invoices i 
            LEFT JOIN jobcards j ON i.job_card_id = j.id 
            LEFT JOIN appointments a ON j.appointment_id = a.id
            LEFT JOIN users u_cust ON i.customer_id = u_cust.id
            LEFT JOIN vehicles v ON a.vehicle_id = v.id
            ORDER BY i.id DESC
        ";
        
        $invoices = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        $stats = [
            'total_revenue' => 0,
            'pending_count' => 0,
            'pending_value' => 0,
            'overdue_count' => 0,
            'overdue_value' => 0
        ];
        
        foreach($invoices as &$inv) {
            $amount = (float)$inv['total_amount'];
            
            // Map 'Unpaid' to 'Pending' for UI consistency
            if ($inv['status'] === 'Unpaid') {
                $inv['status'] = 'Pending';
            }
            // Logic for 'Overdue' (if it's more than 30 days old and Pending)
            $issueDate = strtotime($inv['date']);
            if ($inv['status'] === 'Pending' && time() - $issueDate > 30 * 86400) {
                $inv['status'] = 'Overdue';
            }

            if ($inv['status'] === 'Paid') {
                $stats['total_revenue'] += $amount;
            } elseif ($inv['status'] === 'Pending' || $inv['status'] === 'Pending Approval') {
                $stats['pending_count']++;
                $stats['pending_value'] += $amount;
            } elseif ($inv['status'] === 'Overdue') {
                $stats['overdue_count']++;
                $stats['overdue_value'] += $amount;
            }
            
            // Provide defaults for UI
            if (!$inv['customer_email']) {
                $inv['customer_email'] = strtolower(str_replace(' ', '.', $inv['customer_name'] ?? 'customer')) . '@example.com';
            }
            if (!$inv['vin']) {
                $inv['vin'] = 'Pending';
            }
        }
        
        echo json_encode(['success' => true, 'invoices' => $invoices, 'stats' => $stats]);
    }
    elseif ($action === 'create') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        
        $jobId = (int)($data['job_card_id'] ?? 0);
        $email = $data['customer_email'] ?? 'billing@customer.com';
        $amount = (float)($data['total_amount'] ?? 0);
        
        if ($jobId > 0) {
            // Find customer_id associated with this job card
            $stmt = $pdo->prepare("SELECT a.owner_id FROM jobcards j JOIN appointments a ON j.appointment_id = a.id WHERE j.id = ?");
            $stmt->execute([$jobId]);
            $custId = $stmt->fetchColumn();
            
            if (!$custId) {
                // Fallback customer ID if not found
                $custId = 1;
            }

            $stmt = $pdo->prepare("INSERT INTO invoices (job_card_id, customer_id, customer_email, total_amount, status) VALUES (?, ?, ?, ?, 'Unpaid')");
            $stmt->execute([$jobId, $custId, $email, $amount]);
            $invId = $pdo->lastInsertId();
            
            $invNum = 'INV-' . date('Y') . '-' . str_pad((string)$invId, 4, '0', STR_PAD_LEFT);
            
            echo json_encode(['success' => true, 'invoice_number' => $invNum]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid Job Card']);
        }
    }
    elseif ($action === 'update_amount') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $amount = (float)($data['total_amount'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE invoices SET total_amount = ? WHERE id = ?");
            $stmt->execute([$amount, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
    }
    elseif ($action === 'update_status') {
        header('Content-Type: application/json; charset=utf-8');
        $data = json_decode(file_get_contents('php://input'), true);
        $id = (int)($data['id'] ?? 0);
        $status = $data['status'] ?? 'Paid';
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid ID']);
        }
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
