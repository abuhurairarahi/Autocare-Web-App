<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $filter = $_GET['filter'] ?? 'all';

        $sql = "
            SELECT i.invoice_id as id,
                   COALESCE(i.invoice_number, CONCAT('INV-2026-', LPAD(i.invoice_id, 3, '0'))) as invoice_number,
                   i.job_id as job_card_id,
                   COALESCE(j.code, CONCAT('JC-', i.job_id)) as job_card_code,
                   COALESCE(u.name, 'Customer') as customer_name,
                   COALESCE(u.email, 'customer@example.com') as customer_email,
                   COALESCE(u.phone, 'N/A') as customer_phone,
                   COALESCE(CONCAT(v.make, ' ', v.model), 'Vehicle') as vehicle_name,
                   COALESCE(v.vin, 'VIN-PENDING') as vin,
                   DATE_FORMAT(i.issued_date, '%b %d, %Y') as date,
                   i.total_amount,
                   i.status,
                   i.paid_date,
                   i.pdf_url
            FROM Invoices i
            LEFT JOIN JobCards j ON i.job_id = j.job_id
            LEFT JOIN Users u ON i.customer_id = u.user_id
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            WHERE 1=1
        ";

        $params = [];
        if ($filter !== 'all') {
            $sql .= " AND LOWER(i.status) = ?";
            $params[] = strtolower($filter);
        }

        $sql .= " ORDER BY i.invoice_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        // Calculate summary stats
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Paid'");
        $totalRevenue = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Pending'");
        $pendingStats = $stmt->fetch(PDO::FETCH_NUM);

        $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_amount), 0) FROM Invoices WHERE status = 'Overdue'");
        $overdueStats = $stmt->fetch(PDO::FETCH_NUM);

        echo json_encode([
            'success' => true,
            'data' => $invoices,
            'stats' => [
                'total_revenue' => $totalRevenue,
                'pending_count' => (int) $pendingStats[0],
                'pending_value' => (float) $pendingStats[1],
                'overdue_count' => (int) $overdueStats[0],
                'overdue_value' => (float) $overdueStats[1]
            ]
        ]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? 'create';

        if ($action === 'create') {
            $jobId = intval($input['job_card_id'] ?? $input['job_id'] ?? 0);
            $totalAmount = floatval($input['total_amount'] ?? 0);
            $status = trim($input['status'] ?? 'Pending');

            // Find customer from job card if not provided
            $customerId = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
            if (!$customerId && $jobId) {
                $stmt = $pdo->prepare("
                    SELECT a.owner_id 
                    FROM JobCards j 
                    JOIN Appointments a ON j.appointment_id = a.appointment_id 
                    WHERE j.job_id = ?
                ");
                $stmt->execute([$jobId]);
                $customerId = $stmt->fetchColumn() ?: 101;
            }
            if (!$customerId) $customerId = 101;

            $count = $pdo->query("SELECT COUNT(*) FROM Invoices")->fetchColumn();
            $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($count + 91, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO Invoices (invoice_number, job_id, customer_id, total_amount, status, issued_date)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$invoiceNumber, $jobId, $customerId, $totalAmount, $status]);
            $invoiceId = $pdo->lastInsertId();

            // Log
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'gray', ?)")
                ->execute(["Invoice <strong>#{$invoiceNumber}</strong> generated.", '৳' . number_format($totalAmount, 2)]);

            echo json_encode([
                'success' => true,
                'message' => 'Invoice created successfully',
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber
            ]);
        }
        elseif ($action === 'update_status') {
            $invoiceId = intval($input['invoice_id'] ?? 0);
            $newStatus = trim($input['status'] ?? 'Paid');

            if (!$invoiceId) {
                echo json_encode(['error' => 'Invoice ID required']);
                exit;
            }

            $paidDate = ($newStatus === 'Paid') ? date('Y-m-d H:i:s') : null;

            $stmt = $pdo->prepare("UPDATE Invoices SET status = ?, paid_date = ? WHERE invoice_id = ?");
            $stmt->execute([$newStatus, $paidDate, $invoiceId]);

            // Get invoice number and amount
            $stmt = $pdo->prepare("SELECT invoice_number, total_amount FROM Invoices WHERE invoice_id = ?");
            $stmt->execute([$invoiceId]);
            $inv = $stmt->fetch();
            $num = $inv['invoice_number'] ?? ('INV-' . $invoiceId);
            $amt = $inv['total_amount'] ?? 0;

            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'gray', ?)")
                ->execute(["Invoice <strong>#{$num}</strong> marked as {$newStatus}.", '৳' . number_format($amt, 2)]);

            echo json_encode(['success' => true, 'message' => "Invoice updated to {$newStatus}"]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
