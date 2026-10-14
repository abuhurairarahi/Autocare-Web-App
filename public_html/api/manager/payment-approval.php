<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $status = $_GET['status'] ?? null;
        $search = trim($_GET['search'] ?? '');

        $sql = "
            SELECT jp.job_part_id as id,
                   jp.job_id as job_card_id,
                   COALESCE(j.work_order, CONCAT('#WO-', 2000 + jp.job_id)) as work_order,
                   jp.part_id,
                   COALESCE(sp.name, 'Spare Part') as part_name,
                   CONCAT('PN: ', COALESCE(sp.sku, 'PN-UNKNOWN')) as part_number,
                   jp.quantity,
                   COALESCE(sp.unit, 'Units') as unit,
                   jp.unit_price,
                   COALESCE(jp.total_price, jp.quantity * jp.unit_price) as total_price,
                   COALESCE(m.name, 'Workshop Mechanic') as mechanic_name,
                   COALESCE(
                       CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
                       'ME'
                   ) as mechanic_initials,
                   jp.status,
                   jp.rejection_reason,
                   DATE_FORMAT(jp.requested_at, '%b %d, %Y %H:%i') as requested_at_formatted,
                   jp.requested_at
            FROM JobParts jp
            LEFT JOIN JobCards j ON jp.job_id = j.job_id
            LEFT JOIN SpareParts sp ON jp.part_id = sp.part_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            WHERE 1=1
        ";

        $params = [];
        if ($status && $status !== 'All' && $status !== 'all') {
            $sql .= " AND jp.status = ?";
            $params[] = $status;
        }

        if (!empty($search)) {
            $sql .= " AND (sp.name LIKE ? OR sp.sku LIKE ? OR j.work_order LIKE ? OR m.name LIKE ?)";
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY jp.job_part_id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll();

        // Calculate initials accurately
        foreach ($requests as &$req) {
            $parts = explode(' ', trim($req['mechanic_name']));
            $req['mechanic_initials'] = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            $req['total_price'] = floatval($req['total_price']);
            $req['unit_price'] = floatval($req['unit_price']);
            $req['quantity'] = intval($req['quantity']);
        }

        // Stats
        $stmt = $pdo->query("SELECT COALESCE(SUM(stock_quantity), 0) FROM SpareParts");
        $totalInStock = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM SpareParts WHERE stock_quantity <= reorder_level");
        $lowStockCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_price), 0) FROM JobParts WHERE status = 'Pending Approval'");
        $pendingStats = $stmt->fetch(PDO::FETCH_NUM);

        $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Approved'");
        $approvedCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Rejected'");
        $rejectedCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts");
        $allCount = (int) $stmt->fetchColumn();

        // Available spare parts for dropdown
        $stmt = $pdo->query("SELECT part_id, sku, name, category, price, stock_quantity, unit FROM SpareParts ORDER BY name ASC");
        $spareParts = $stmt->fetchAll();

        // Available active job cards for dropdown
        $stmt = $pdo->query("SELECT job_id, code, work_order, service_text FROM JobCards WHERE status != 'Completed' ORDER BY job_id DESC");
        $jobCards = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $requests,
            'stats' => [
                'total_in_stock' => $totalInStock,
                'low_stock_count' => $lowStockCount,
                'pending_requests' => (int) $pendingStats[0],
                'pending_value' => (float) $pendingStats[1],
                'badge_pending' => (int) $pendingStats[0],
                'badge_approved' => $approvedCount,
                'badge_rejected' => $rejectedCount,
                'badge_all' => $allCount
            ],
            'spare_parts' => $spareParts,
            'job_cards' => $jobCards
        ]);
    }
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $action = $input['action'] ?? 'approve';

        if ($action === 'approve') {
            $reqId = intval($input['request_id'] ?? 0);
            if (!$reqId) {
                echo json_encode(['error' => 'Request ID is required']);
                exit;
            }

            // Get request info
            $stmt = $pdo->prepare("SELECT jp.*, sp.name as part_name, j.work_order FROM JobParts jp JOIN SpareParts sp ON jp.part_id = sp.part_id LEFT JOIN JobCards j ON jp.job_id = j.job_id WHERE jp.job_part_id = ?");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();

            if (!$req) {
                echo json_encode(['error' => 'Part request not found']);
                exit;
            }

            // Update status
            $pdo->prepare("UPDATE JobParts SET status = 'Approved' WHERE job_part_id = ?")->execute([$reqId]);

            // Deduct stock quantity
            $pdo->prepare("UPDATE SpareParts SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE part_id = ?")
                ->execute([$req['quantity'], $req['part_id']]);

            // Log activity
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                ->execute(["Spare part request approved: <strong>{$req['part_name']}</strong> for {$req['work_order']}.", '৳' . number_format($req['total_price'], 2)]);

            echo json_encode(['success' => true, 'message' => "Part request approved"]);
        }
        elseif ($action === 'reject') {
            $reqId = intval($input['request_id'] ?? 0);
            $reason = trim($input['reason'] ?? 'Declined by workshop manager');

            $stmt = $pdo->prepare("SELECT jp.*, sp.name as part_name, j.work_order FROM JobParts jp JOIN SpareParts sp ON jp.part_id = sp.part_id LEFT JOIN JobCards j ON jp.job_id = j.job_id WHERE jp.job_part_id = ?");
            $stmt->execute([$reqId]);
            $req = $stmt->fetch();

            $pdo->prepare("UPDATE JobParts SET status = 'Rejected', rejection_reason = ? WHERE job_part_id = ?")
                ->execute([$reason, $reqId]);

            $partName = $req['part_name'] ?? 'Part';
            $workOrder = $req['work_order'] ?? '#WO';
            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'red', 'Rejected')")
                ->execute(["Spare part request rejected: <strong>{$partName}</strong> for {$workOrder}.", 'Declined']);

            echo json_encode(['success' => true, 'message' => "Part request rejected"]);
        }
        elseif ($action === 'approve_all') {
            $stmt = $pdo->query("SELECT job_part_id, part_id, quantity FROM JobParts WHERE status = 'Pending Approval'");
            $pending = $stmt->fetchAll();

            foreach ($pending as $p) {
                $pdo->prepare("UPDATE JobParts SET status = 'Approved' WHERE job_part_id = ?")->execute([$p['job_part_id']]);
                $pdo->prepare("UPDATE SpareParts SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE part_id = ?")
                    ->execute([$p['quantity'], $p['part_id']]);
            }

            $count = count($pending);
            if ($count > 0) {
                $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                    ->execute(["Bulk approved <strong>{$count}</strong> spare parts requests.", 'Approved All']);
            }

            echo json_encode(['success' => true, 'message' => "Approved {$count} pending requests", 'count' => $count]);
        }
        elseif ($action === 'create_request') {
            $jobId = intval($input['job_card_id'] ?? 0);
            $partId = intval($input['part_id'] ?? 0);
            $quantity = max(1, intval($input['quantity'] ?? 1));

            if (!$jobId || !$partId) {
                echo json_encode(['error' => 'Job Card and Spare Part required']);
                exit;
            }

            // Fetch part price
            $stmt = $pdo->prepare("SELECT name, price FROM SpareParts WHERE part_id = ?");
            $stmt->execute([$partId]);
            $part = $stmt->fetch();

            $unitPrice = floatval($part['price'] ?? 0);
            $totalPrice = $unitPrice * $quantity;

            $stmt = $pdo->prepare("
                INSERT INTO JobParts (job_id, part_id, quantity, unit_price, total_price, status, requested_at)
                VALUES (?, ?, ?, ?, ?, 'Pending Approval', NOW())
            ");
            $stmt->execute([$jobId, $partId, $quantity, $unitPrice, $totalPrice]);
            $newReqId = $pdo->lastInsertId();

            // Fetch work order for log
            $stmt = $pdo->prepare("SELECT work_order FROM JobCards WHERE job_id = ?");
            $stmt->execute([$jobId]);
            $wo = $stmt->fetchColumn() ?: ('#WO-' . $jobId);

            $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")
                ->execute(["Spare part requested: <strong>{$part['name']}</strong> for {$wo}.", '৳' . number_format($totalPrice, 2)]);

            echo json_encode([
                'success' => true,
                'message' => 'Spare part request created',
                'request_id' => $newReqId
            ]);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
