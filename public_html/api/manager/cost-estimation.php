<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $estimateId = $_GET['id'] ?? null;

        $sql = "
            SELECT e.estimate_id as id,
                   e.code,
                   e.job_id as job_card_id,
                   COALESCE(j.code, CONCAT('JC-', j.job_id)) as job_card_code,
                   COALESCE(j.service_text, 'Vehicle Repair') as job_card_title,
                   COALESCE(u.name, 'Customer') as customer_name,
                   COALESCE(m.name, 'Workshop Manager') as mechanic_name,
                   e.status,
                   COALESCE(e.sent_date, '-') as sent_date,
                   e.line_items,
                   e.subtotal,
                   e.tax_rate,
                   e.tax_amount,
                   e.total_estimated_cost
            FROM RepairEstimates e
            LEFT JOIN JobCards j ON e.job_id = j.job_id
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Users u ON a.owner_id = u.user_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            WHERE 1=1
        ";

        $params = [];
        if ($estimateId) {
            $sql .= " AND e.estimate_id = ?";
            $params[] = intval($estimateId);
        }

        $sql .= " ORDER BY e.estimate_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $estimates = $stmt->fetchAll();

        foreach ($estimates as &$est) {
            $est['line_items'] = json_decode($est['line_items'] ?? '[]', true) ?: [];
            $est['subtotal'] = floatval($est['subtotal']);
            $est['tax_amount'] = floatval($est['tax_amount']);
            $est['total_estimated_cost'] = floatval($est['total_estimated_cost']);
        }

        echo json_encode([
            'success' => true,
            'data' => $estimateId && count($estimates) > 0 ? $estimates[0] : $estimates
        ]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $id = !empty($input['id']) ? intval($input['id']) : null;
        $jobId = intval($input['job_card_id'] ?? $input['job_id'] ?? 1045);
        $status = trim($input['status'] ?? 'Draft');
        $sentDate = trim($input['sent_date'] ?? '-');
        $lineItems = $input['line_items'] ?? [];
        $subtotal = floatval($input['subtotal'] ?? 0);
        $taxRate = floatval($input['tax_rate'] ?? 0.085);
        $taxAmount = floatval($input['tax_amount'] ?? ($subtotal * $taxRate));
        $totalCost = floatval($input['total_estimated_cost'] ?? ($subtotal + $taxAmount));

        $lineItemsJson = json_encode($lineItems);

        if ($id) {
            // Update existing
            $stmt = $pdo->prepare("
                UPDATE RepairEstimates
                SET status = ?, sent_date = ?, line_items = ?, subtotal = ?, tax_rate = ?, tax_amount = ?, total_estimated_cost = ?
                WHERE estimate_id = ?
            ");
            $stmt->execute([$status, $sentDate, $lineItemsJson, $subtotal, $taxRate, $taxAmount, $totalCost, $id]);
            $estimateId = $id;

            // Fetch code
            $stmt = $pdo->prepare("SELECT code FROM RepairEstimates WHERE estimate_id = ?");
            $stmt->execute([$estimateId]);
            $code = $stmt->fetchColumn();
        } else {
            // Create new
            $count = $pdo->query("SELECT COUNT(*) FROM RepairEstimates")->fetchColumn();
            $code = 'EST-' . date('Y') . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("
                INSERT INTO RepairEstimates (code, job_id, status, sent_date, line_items, subtotal, tax_rate, tax_amount, total_estimated_cost)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$code, $jobId, $status, $sentDate, $lineItemsJson, $subtotal, $taxRate, $taxAmount, $totalCost]);
            $estimateId = $pdo->lastInsertId();
        }

        // Update JobCard estimated_cost
        if ($jobId && $totalCost > 0) {
            $pdo->prepare("UPDATE JobCards SET estimated_cost = ? WHERE job_id = ?")->execute([$totalCost, $jobId]);
        }

        // Log activity
        $logText = "Cost Estimate <strong>{$code}</strong> updated ({$status}).";
        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
            ->execute([$logText, '৳' . number_format($totalCost, 2)]);

        echo json_encode([
            'success' => true,
            'message' => 'Estimate saved successfully',
            'estimate_id' => $estimateId,
            'code' => $code,
            'total_estimated_cost' => $totalCost
        ]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
