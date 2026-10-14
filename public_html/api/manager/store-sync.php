<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $data = [];

        // 1. Users
        $stmt = $pdo->query("
            SELECT user_id as id,
                   SUBSTRING_INDEX(email, '@', 1) as username,
                   name,
                   email,
                   phone,
                   role,
                   COALESCE(status, 'Active') as status,
                   specialty,
                   experience,
                   COALESCE(workload, 0) as workload,
                   COALESCE(avatar, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150') as avatar
            FROM Users
            ORDER BY user_id ASC
        ");
        $data['users'] = $stmt->fetchAll();

        // 2. Vehicles
        $stmt = $pdo->query("
            SELECT vehicle_id as id,
                   owner_id,
                   CONCAT(make, ' ', model, ' (', year, ')') as model,
                   license_plate as registration_number,
                   vin
            FROM Vehicles
            ORDER BY vehicle_id ASC
        ");
        $data['vehicles'] = $stmt->fetchAll();

        // 3. Service Categories
        $stmt = $pdo->query("
            SELECT category_id as id,
                   name,
                   description,
                   base_rate
            FROM ServiceCategories
            ORDER BY category_id ASC
        ");
        $data['categories'] = $stmt->fetchAll();

        // 4. Spare Parts
        $stmt = $pdo->query("
            SELECT part_id as id,
                   name,
                   sku as part_number,
                   price,
                   stock_quantity as quantity_in_stock,
                   reorder_level as low_stock_threshold,
                   unit
            FROM SpareParts
            ORDER BY part_id ASC
        ");
        $data['spareParts'] = $stmt->fetchAll();

        // 5. Service Requests (Appointments)
        $stmt = $pdo->query("
            SELECT a.appointment_id as id,
                   COALESCE(a.code, CONCAT('BRQ-2026-', LPAD(a.appointment_id, 3, '0'))) as code,
                   a.owner_id,
                   a.vehicle_id,
                   a.service_category_id as category_id,
                   DATE_FORMAT(a.preferred_date, '%b %d, %H:%i') as requested_date,
                   COALESCE(a.priority, 'Normal') as priority,
                   a.issue_description as description,
                   a.status,
                   a.created_at
            FROM Appointments a
            ORDER BY a.appointment_id DESC
        ");
        $data['serviceRequests'] = $stmt->fetchAll();

        // 6. Job Cards
        $stmt = $pdo->query("
            SELECT j.job_id as id,
                   COALESCE(j.code, CONCAT('JC-', j.job_id)) as code,
                   COALESCE(j.work_order, CONCAT('#WO-', 2000 + j.job_id)) as work_order,
                   COALESCE(j.appointment_id, 0) as request_id,
                   COALESCE(u.name, 'Customer') as customer_name,
                   CONCAT(v.make, ' ', v.model, ' • ', v.license_plate) as vehicle_details,
                   CONCAT(v.make, ' ', v.model) as vehicle_title,
                   COALESCE(v.vin, 'VIN-PENDING') as vin,
                   COALESCE(j.manager_id, 2) as manager_id,
                   j.mechanic_id,
                   COALESCE(m.name, 'Unassigned') as mechanic_name,
                   COALESCE(
                       CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
                       'UA'
                   ) as mechanic_initials,
                   COALESCE(a.priority, 'Normal') as priority,
                   j.status,
                   COALESCE(j.kanban_stage, 'PENDING') as kanban_stage,
                   COALESCE(j.progress_percentage, 15) as progress_percentage,
                   j.estimated_cost,
                   DATE_FORMAT(j.created_at, '%b %d, %Y') as date_opened,
                   COALESCE(j.delivery_date, 'TBD') as delivery_date,
                   COALESCE(j.service_text, j.fault_report, 'General Repair') as service_text
            FROM JobCards j
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Users u ON a.owner_id = u.user_id
            LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            ORDER BY j.job_id DESC
        ");
        $jobCards = $stmt->fetchAll();
        foreach ($jobCards as &$card) {
            if ($card['mechanic_name'] !== 'Unassigned') {
                $parts = explode(' ', trim($card['mechanic_name']));
                $card['mechanic_initials'] = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
            } else {
                $card['mechanic_initials'] = 'UA';
            }
            $card['estimated_cost'] = floatval($card['estimated_cost']);
            $card['progress_percentage'] = intval($card['progress_percentage']);
        }
        $data['jobCards'] = $jobCards;

        // 7. Repair Estimates
        $stmt = $pdo->query("
            SELECT e.estimate_id as id,
                   e.code,
                   e.job_id as job_card_id,
                   COALESCE(j.code, CONCAT('JC-', e.job_id)) as job_card_code,
                   COALESCE(j.service_text, 'Service') as job_card_title,
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
            ORDER BY e.estimate_id DESC
        ");
        $estimates = $stmt->fetchAll();
        foreach ($estimates as &$est) {
            $est['line_items'] = json_decode($est['line_items'] ?? '[]', true) ?: [];
            $est['subtotal'] = floatval($est['subtotal']);
            $est['tax_rate'] = floatval($est['tax_rate']);
            $est['tax_amount'] = floatval($est['tax_amount']);
            $est['total_estimated_cost'] = floatval($est['total_estimated_cost']);
        }
        $data['estimates'] = $estimates;

        // 8. Job Card Parts (Spare Parts Approvals)
        $stmt = $pdo->query("
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
                   COALESCE(m.name, 'Mechanic') as mechanic_name,
                   COALESCE(
                       CONCAT(SUBSTRING_INDEX(m.name, ' ', 1), SUBSTRING(SUBSTRING_INDEX(m.name, ' ', -1), 1, 1)),
                       'ME'
                   ) as mechanic_initials,
                   jp.status,
                   jp.rejection_reason,
                   jp.requested_at
            FROM JobParts jp
            LEFT JOIN JobCards j ON jp.job_id = j.job_id
            LEFT JOIN SpareParts sp ON jp.part_id = sp.part_id
            LEFT JOIN Users m ON j.mechanic_id = m.user_id
            ORDER BY jp.job_part_id DESC
        ");
        $parts = $stmt->fetchAll();
        foreach ($parts as &$p) {
            $p['quantity'] = intval($p['quantity']);
            $p['unit_price'] = floatval($p['unit_price']);
            $p['total_price'] = floatval($p['total_price']);
            $pParts = explode(' ', trim($p['mechanic_name']));
            $p['mechanic_initials'] = strtoupper(substr($pParts[0], 0, 1) . (isset($pParts[1]) ? substr($pParts[1], 0, 1) : ''));
        }
        $data['jobCardParts'] = $parts;

        // 9. Invoices
        $stmt = $pdo->query("
            SELECT i.invoice_id as id,
                   COALESCE(i.invoice_number, CONCAT('INV-2026-', LPAD(i.invoice_id, 3, '0'))) as invoice_number,
                   i.job_id as job_card_id,
                   COALESCE(u.name, 'Customer') as customer_name,
                   COALESCE(u.email, 'customer@example.com') as customer_email,
                   COALESCE(CONCAT(v.make, ' ', v.model), 'Vehicle') as vehicle_name,
                   COALESCE(v.vin, 'VIN-PENDING') as vin,
                   DATE_FORMAT(i.issued_date, '%b %d, %Y') as date,
                   i.total_amount,
                   i.status,
                   i.issued_date as created_at
            FROM Invoices i
            LEFT JOIN JobCards j ON i.job_id = j.job_id
            LEFT JOIN Users u ON i.customer_id = u.user_id
            LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
            LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
            ORDER BY i.invoice_id DESC
        ");
        $invoices = $stmt->fetchAll();
        foreach ($invoices as &$inv) {
            $inv['total_amount'] = floatval($inv['total_amount']);
        }
        $data['invoices'] = $invoices;

        // 10. Chat Messages
        $stmt = $pdo->query("
            SELECT m.message_id as id,
                   m.sender_id,
                   m.receiver_id,
                   u.name as sender_name,
                   COALESCE(u.specialty, u.role) as sender_role,
                   COALESCE(m.job_tag, 'JOB #8492') as job_tag,
                   m.message_text as message,
                   DATE_FORMAT(m.created_at, '%h:%i %p') as time,
                   (m.sender_id != 2) as is_incoming,
                   m.attachment_url
            FROM ChatMessages m
            JOIN Users u ON m.sender_id = u.user_id
            ORDER BY m.created_at ASC
        ");
        $chat = $stmt->fetchAll();
        foreach ($chat as &$c) {
            $c['is_incoming'] = (bool)$c['is_incoming'];
            $attachments = [];
            if (!empty($c['attachment_url'])) {
                $decoded = json_decode($c['attachment_url'], true);
                $attachments = is_array($decoded) ? $decoded : [$c['attachment_url']];
            }
            $c['attachments'] = $attachments;
        }
        $data['chatMessages'] = $chat;

        // 11. Repair Timeline
        $stmt = $pdo->query("
            SELECT timeline_id as id,
                   job_id as job_card_id,
                   stage,
                   updated_by,
                   DATE_FORMAT(updated_at, '%b %d, %H:%i') as updated_at
            FROM RepairTimeline
            ORDER BY timeline_id ASC
        ");
        $data['repairTimeline'] = $stmt->fetchAll();

        // 12. Recent Activities
        $stmt = $pdo->query("
            SELECT activity_id as id,
                   text,
                   DATE_FORMAT(created_at, '%h:%i %p') as time,
                   subtext,
                   type
            FROM ActivityLogs
            ORDER BY activity_id DESC
            LIMIT 15
        ");
        $data['recentActivities'] = $stmt->fetchAll();

        // 13. Notices
        $stmt = $pdo->query("
            SELECT broadcast_id as id,
                   title,
                   content,
                   audience as target_audience,
                   DATE_FORMAT(created_at, '%b %d') as created_at
            FROM ServiceBroadcasts
            ORDER BY broadcast_id DESC
        ");
        $data['notices'] = $stmt->fetchAll();

        echo json_encode(['success' => true, 'data' => $data]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
