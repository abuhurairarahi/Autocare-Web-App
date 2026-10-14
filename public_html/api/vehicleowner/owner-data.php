<?php
/**
 * owner-data.php
 * Read queries for the Vehicle Owner panel, shared by the server-rendered pages
 * and the JSON endpoints. Every function is scoped to the given $ownerId (a Users.id),
 * which callers must take from the session.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../repair-stages.php';

// Booking time slots: form value => [label, start time stored in Appointments.preferred_date]
const TIME_SLOTS = [
    'morning' => ['Morning (08:00 - 12:00)', '09:00:00'],
    'afternoon' => ['Afternoon (12:00 - 16:00)', '13:00:00'],
    'evening' => ['Evening (16:00 - 18:00)', '16:00:00'],
];

// Invoices.status values that still need the owner to pay
const UNPAID_INVOICE_STATUSES = ['Unpaid', 'Pending Approval'];

function booking_workshops(PDO $pdo): array
{
    return $pdo->query("SELECT id, name, location FROM Workshops ORDER BY name")->fetchAll();
}

function booking_categories(PDO $pdo): array
{
    return $pdo->query("SELECT id, name, description FROM ServiceCategories ORDER BY id")->fetchAll();
}

/** Vehicles with their latest open job status and next booked appointment. */
function owner_vehicles(PDO $pdo, int $ownerId): array
{
    $stmt = $pdo->prepare("
        SELECT v.id, v.make, v.model, v.year, v.license_plate, v.vin,
               (SELECT j.status FROM JobCards j
                  JOIN Appointments a ON j.appointment_id = a.id
                 WHERE a.vehicle_id = v.id AND a.owner_id = v.owner_id
                   AND j.status NOT IN ('Completed', 'Delivered')
                 ORDER BY j.id DESC LIMIT 1) AS active_job_status,
               (SELECT MIN(a.preferred_date) FROM Appointments a
                 WHERE a.vehicle_id = v.id AND a.owner_id = v.owner_id
                   AND a.status IN ('Pending', 'Approved') AND a.preferred_date >= NOW()) AS next_appointment
          FROM Vehicles v
         WHERE v.owner_id = ?
         ORDER BY v.created_at DESC, v.id DESC
    ");
    $stmt->execute([$ownerId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['year'] = (int) $row['year'];
        $row['in_shop'] = $row['active_job_status'] !== null;
    }
    return $rows;
}

/**
 * Job cards for the owner's vehicles (joined through Appointments).
 * $active: true = open jobs only, false = finished jobs only, null = all.
 */
function owner_jobs(PDO $pdo, int $ownerId, ?bool $active = null, ?int $jobId = null): array
{
    $sql = "
        SELECT j.id, j.created_at, j.status AS db_status, " . display_status_sql('j') . " AS shown_status,
               j.fault_report, j.estimated_cost, j.delivery_date, j.start_date, j.completion_date,
               a.id AS appointment_id, a.preferred_date, a.issue_description,
               v.id AS vehicle_id, v.make, v.model, v.year, v.license_plate,
               w.name AS workshop_name,
               sc.name AS category_name,
               j.mechanic_id, m.name AS mechanic_name,
               j.manager_id
          FROM JobCards j
          JOIN Appointments a ON j.appointment_id = a.id
          JOIN Vehicles v ON a.vehicle_id = v.id
          LEFT JOIN Workshops w ON a.workshop_id = w.id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.id
          LEFT JOIN Users m ON j.mechanic_id = m.id
         WHERE a.owner_id = ?
    ";
    $params = [$ownerId];
    if ($active === true) {
        $sql .= " AND j.status NOT IN ('Completed', 'Delivered')";
    } elseif ($active === false) {
        $sql .= " AND j.status IN ('Completed', 'Delivered')";
    }
    if ($jobId !== null) {
        $sql .= " AND j.id = ?";
        $params[] = $jobId;
    }
    $sql .= " ORDER BY COALESCE(j.completion_date, j.start_date, j.created_at) DESC, j.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['status'] = display_status($row['db_status'], $row['shown_status']);
        unset($row['shown_status']);
        $row['code'] = job_code($row['id'], $row['created_at']);
        $row['work_order'] = null;
        $row['progress_percentage'] = status_progress($row['status'])['progress'];
        $row['service_text'] = $row['category_name'] ?: ($row['issue_description'] ?: ($row['fault_report'] ?: 'General Service'));
        $row['priority'] = 'Normal';
        $row['mechanic_specialty'] = $row['mechanic_id'] ? 'Mechanic' : null;
        $row['mechanic_avatar'] = null;
        $row['estimated_cost'] = (float) $row['estimated_cost'];
        $row['step_index'] = repair_step_index($row['status']);
    }
    return $rows;
}

/**
 * One of the owner's jobs with its timeline, photos and approved estimate total,
 * or null when the job does not exist or belongs to someone else.
 */
function owner_job_detail(PDO $pdo, int $ownerId, int $jobId): ?array
{
    $jobs = owner_jobs($pdo, $ownerId, null, $jobId);
    if (!$jobs) {
        return null;
    }
    $job = $jobs[0];

    $stmt = $pdo->prepare("
        SELECT t.stage, t.updated_at, u.name AS updated_by_name
          FROM RepairTimeline t
          LEFT JOIN Users u ON t.updated_by = u.id
         WHERE t.job_card_id = ?
         ORDER BY t.updated_at ASC, t.id ASC
    ");
    $stmt->execute([$jobId]);
    $job['timeline'] = $stmt->fetchAll();

    // Latest time each customer-facing step was reached
    $job['step_times'] = [];
    foreach ($job['timeline'] as $entry) {
        $job['step_times'][repair_step_index($entry['stage'])] = $entry['updated_at'];
    }

    $stmt = $pdo->prepare("SELECT id, photo_url, type, description, uploaded_at FROM JobCardPhotos WHERE job_card_id = ? ORDER BY uploaded_at DESC, id DESC");
    $stmt->execute([$jobId]);
    $job['photos'] = [];
    foreach ($stmt->fetchAll() as $photo) {
        $photo['photo_url'] = safe_image_url($photo['photo_url']);
        if ($photo['photo_url'] !== '') {
            $job['photos'][] = $photo;
        }
    }

    $stmt = $pdo->prepare("SELECT total_estimated_cost FROM RepairEstimates WHERE job_card_id = ? AND status = 'Approved' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$jobId]);
    $approved = $stmt->fetchColumn();
    $job['approved_total'] = $approved === false ? null : (float) $approved;

    return $job;
}

/** Invoices billed to the owner. Optional filters: vehicle_id, date (Y-m-d issued on), id, limit. */
function owner_invoices(PDO $pdo, int $ownerId, array $filters = []): array
{
    $sql = "
        SELECT i.id, i.job_card_id AS job_id, i.total_amount, i.status, i.issued_date, i.paid_date, i.pdf_url,
               j.created_at AS job_created_at, j.fault_report,
               sc.name AS category_name, a.issue_description,
               v.id AS vehicle_id, v.make, v.model, v.year, v.license_plate
          FROM Invoices i
          JOIN JobCards j ON i.job_card_id = j.id
          LEFT JOIN Appointments a ON j.appointment_id = a.id AND a.owner_id = i.customer_id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.id
          LEFT JOIN Vehicles v ON a.vehicle_id = v.id
         WHERE i.customer_id = ?
    ";
    $params = [$ownerId];
    if (!empty($filters['vehicle_id'])) {
        $sql .= " AND v.id = ?";
        $params[] = (int) $filters['vehicle_id'];
    }
    if (!empty($filters['date'])) {
        $sql .= " AND DATE(i.issued_date) = ?";
        $params[] = $filters['date'];
    }
    if (!empty($filters['id'])) {
        $sql .= " AND i.id = ?";
        $params[] = (int) $filters['id'];
    }
    $sql .= " ORDER BY i.issued_date DESC, i.id DESC";
    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . max(1, (int) $filters['limit']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['job_id'] = (int) $row['job_id'];
        $row['total_amount'] = (float) $row['total_amount'];
        $row['invoice_number'] = invoice_number($row['id'], $row['issued_date']);
        $row['job_code'] = job_code($row['job_id'], $row['job_created_at']);
        $row['service_text'] = $row['category_name'] ?: ($row['issue_description'] ?: $row['fault_report']);
        unset($row['job_created_at'], $row['category_name'], $row['issue_description'], $row['fault_report']);
    }
    return $rows;
}

/** Paid / pending / overdue counts and spend for the owner's invoices. */
function owner_invoice_stats(PDO $pdo, int $ownerId): array
{
    $stmt = $pdo->prepare("
        SELECT
            SUM(status = 'Paid') AS paid_count,
            SUM(status = 'Paid' AND YEAR(COALESCE(paid_date, issued_date)) = YEAR(CURDATE()) AND MONTH(COALESCE(paid_date, issued_date)) = MONTH(CURDATE())) AS paid_this_month,
            SUM(status IN ('Unpaid', 'Pending Approval')) AS pending_count,
            SUM(status = 'Unpaid' AND issued_date < CURDATE() - INTERVAL 30 DAY) AS overdue_count,
            COALESCE(SUM(CASE WHEN status = 'Paid' AND YEAR(COALESCE(paid_date, issued_date)) = YEAR(CURDATE()) THEN total_amount END), 0) AS spend_ytd,
            COUNT(*) AS total_count
          FROM Invoices
         WHERE customer_id = ?
    ");
    $stmt->execute([$ownerId]);
    $row = $stmt->fetch();
    $stats = array_map('intval', array_diff_key($row, ['spend_ytd' => 1]));
    $stats['spend_ytd'] = (float) $row['spend_ytd'];
    return $stats;
}

/** Approved/used parts and logged labor of a job, as invoice/estimate line items. */
function job_line_items(PDO $pdo, int $jobId, bool $approvedPartsOnly): array
{
    $stmt = $pdo->prepare("
        SELECT sp.name AS description, jp.quantity, jp.unit_price, ROUND(jp.quantity * jp.unit_price, 2) AS total
          FROM JobCardParts jp JOIN SpareParts sp ON jp.part_id = sp.id
         WHERE jp.job_card_id = ? AND jp.status " . ($approvedPartsOnly ? "IN ('Approved', 'Used')" : "<> 'Rejected'") . "
         ORDER BY jp.id
    ");
    $stmt->execute([$jobId]);
    $parts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT description, hours, hourly_rate, ROUND(hours * hourly_rate, 2) AS total FROM JobCardLabor WHERE job_card_id = ? ORDER BY id");
    $stmt->execute([$jobId]);
    $labor = $stmt->fetchAll();

    return ['parts' => $parts, 'labor' => $labor];
}

/** One invoice with the job's approved parts and logged labor as its line items. */
function owner_invoice_detail(PDO $pdo, int $ownerId, int $invoiceId): ?array
{
    $rows = owner_invoices($pdo, $ownerId, ['id' => $invoiceId]);
    if (!$rows) {
        return null;
    }
    $invoice = $rows[0];
    $invoice['pdf_url'] = safe_image_url($invoice['pdf_url']) ?: null;
    return $invoice + job_line_items($pdo, $invoice['job_id'], true);
}

/** Appointments still ahead (Pending/Approved, preferred date today or later); $limit null = all. */
function owner_upcoming_appointments(PDO $pdo, int $ownerId, ?int $limit = 5): array
{
    $stmt = $pdo->prepare("
        SELECT a.id, a.created_at, a.vehicle_id, a.preferred_date, a.status, a.issue_description,
               v.make, v.model, v.year, v.license_plate,
               w.name AS workshop_name, w.location AS workshop_location,
               sc.name AS category_name
          FROM Appointments a
          JOIN Vehicles v ON a.vehicle_id = v.id
          LEFT JOIN Workshops w ON a.workshop_id = w.id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.id
         WHERE a.owner_id = ? AND a.status IN ('Pending', 'Approved') AND a.preferred_date >= CURDATE()
         ORDER BY a.preferred_date ASC" . ($limit === null ? '' : "
         LIMIT " . max(1, $limit)));
    $stmt->execute([$ownerId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['code'] = appointment_code($row['id'], $row['created_at']);
    }
    return $rows;
}

/**
 * Estimates on the owner's jobs. 'Pending Approval' ones are awaiting the owner.
 * Line items are the job's non-rejected parts and its logged labor; the schema stores
 * only the estimate total, so it is shown as the subtotal with no separate tax.
 */
function owner_estimates(PDO $pdo, int $ownerId, ?int $estimateId = null): array
{
    $sql = "
        SELECT e.id, e.job_card_id AS job_id, e.status, e.total_estimated_cost, e.created_at,
               j.created_at AS job_created_at, j.fault_report,
               sc.name AS category_name, a.issue_description,
               v.make, v.model, v.year, v.license_plate,
               m.name AS mechanic_name
          FROM RepairEstimates e
          JOIN JobCards j ON e.job_card_id = j.id
          JOIN Appointments a ON j.appointment_id = a.id
          JOIN Vehicles v ON a.vehicle_id = v.id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.id
          LEFT JOIN Users m ON j.mechanic_id = m.id
         WHERE a.owner_id = ?
    ";
    $params = [$ownerId];
    if ($estimateId !== null) {
        $sql .= " AND e.id = ?";
        $params[] = $estimateId;
    }
    $sql .= " ORDER BY (e.status = 'Pending Approval') DESC, e.created_at DESC, e.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['job_id'] = (int) $row['job_id'];
        $row['code'] = estimate_code($row['id'], $row['created_at']);
        $row['job_code'] = job_code($row['job_id'], $row['job_created_at']);
        $row['service_text'] = $row['category_name'] ?: ($row['issue_description'] ?: 'Vehicle Repair');
        $row['mechanic_specialty'] = $row['mechanic_name'] ? 'Mechanic' : null;
        $row['sent_date'] = '-';
        unset($row['job_created_at'], $row['category_name'], $row['issue_description']);

        $lines = job_line_items($pdo, $row['job_id'], false);
        $row['line_items'] = [];
        foreach ($lines['parts'] as $p) {
            $row['line_items'][] = ['description' => $p['description'], 'quantity' => (int) $p['quantity'], 'unit_price' => (float) $p['unit_price'], 'total' => (float) $p['total']];
        }
        foreach ($lines['labor'] as $l) {
            $row['line_items'][] = ['description' => 'Labor: ' . $l['description'], 'hours_or_qty' => (float) $l['hours'], 'unit_price' => (float) $l['hourly_rate'], 'total' => (float) $l['total']];
        }
        $row['total_estimated_cost'] = (float) $row['total_estimated_cost'];
        $row['subtotal'] = $row['total_estimated_cost'];
        $row['tax_rate'] = 0.0;
        $row['tax_amount'] = 0.0;
        $row['awaiting_approval'] = $row['status'] === 'Pending Approval';
    }
    return $rows;
}

/** Finished jobs with their invoice, plus totals for the Service History page. */
function owner_service_history(PDO $pdo, int $ownerId, ?int $vehicleId = null): array
{
    $invoiceByJob = [];
    foreach (owner_invoices($pdo, $ownerId) as $inv) {
        $invoiceByJob[$inv['job_id']] ??= $inv;
    }

    $records = [];
    foreach (owner_jobs($pdo, $ownerId, false) as $job) {
        if ($vehicleId && (int) $job['vehicle_id'] !== $vehicleId) {
            continue;
        }
        $invoice = $invoiceByJob[$job['id']] ?? null;
        $job['service_date'] = $job['completion_date'] ?: ($job['start_date'] ?: $job['created_at']);
        $job['invoice_id'] = $invoice['id'] ?? null;
        $job['invoice_number'] = $invoice['invoice_number'] ?? null;
        $job['cost'] = $invoice ? $invoice['total_amount'] : $job['estimated_cost'];
        $records[] = $job;
    }

    $stats = owner_invoice_stats($pdo, $ownerId);
    return [
        'records' => $records,
        'total_services' => count($records),
        'last_service' => $records[0] ?? null,
        'spend_ytd' => $stats['spend_ytd'],
    ];
}

/**
 * Notification feed derived from existing tables (there is no notifications table):
 * appointments, estimates awaiting approval, invoices, repair stage changes, received
 * chat messages, active service offers and published broadcasts. Only chat messages
 * have a read flag. Each item: category, icon, color, title, body, time, unread, link.
 */
function owner_notifications(PDO $pdo, int $ownerId, int $limit = 60): array
{
    $items = [];
    $add = function (string $category, string $icon, string $color, string $title, string $body, ?string $time, string $link, bool $unread = false) use (&$items) {
        if ($time) {
            $items[] = compact('category', 'icon', 'color', 'title', 'body', 'time', 'link', 'unread');
        }
    };

    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.preferred_date, a.created_at, v.make, v.model
          FROM Appointments a JOIN Vehicles v ON a.vehicle_id = v.id
         WHERE a.owner_id = ?");
    $stmt->execute([$ownerId]);
    foreach ($stmt->fetchAll() as $a) {
        $code = appointment_code((int) $a['id'], $a['created_at']);
        $when = date('M d, g:i A', strtotime($a['preferred_date']));
        $vehicle = $a['make'] . ' ' . $a['model'];
        $map = [
            'Pending' => ['Booking Request Received', "Your request {$code} for the {$vehicle} on {$when} is waiting for workshop confirmation."],
            'Approved' => ['Appointment Confirmed', "Your service appointment for the {$vehicle} on {$when} is confirmed."],
            'Rejected' => ['Booking Declined', "The workshop could not accept {$code} for {$when}. Please choose another slot."],
            'Cancelled' => ['Appointment Cancelled', "{$code} for the {$vehicle} was cancelled."],
            'Completed' => ['Appointment Completed', "{$code} for the {$vehicle} is complete."],
        ];
        [$title, $body] = $map[$a['status']] ?? ['Appointment Update', $code . ': ' . $a['status']];
        $add('appointments', 'fa-calendar-check', 'gray', $title, $body, $a['created_at'], 'vehicleowner-book-appointment.php');
    }

    foreach (owner_estimates($pdo, $ownerId) as $est) {
        if ($est['awaiting_approval']) {
            $add('billing', 'fa-file-invoice-dollar', 'blue', 'New Estimate Received',
                "Estimate {$est['code']} for {$est['service_text']} (" . money($est['total_estimated_cost']) . ') is waiting for your approval.',
                $est['created_at'], 'vehicleowner-repair-estimates.php');
        }
    }

    foreach (owner_invoices($pdo, $ownerId) as $inv) {
        if ($inv['status'] === 'Paid') {
            $add('billing', 'fa-receipt', 'gray', 'Invoice Paid',
                'Payment of ' . money($inv['total_amount']) . " for invoice {$inv['invoice_number']} was received. Thank you!",
                $inv['paid_date'] ?: $inv['issued_date'], 'vehicleowner-invoices.php');
        } else {
            $add('billing', 'fa-receipt', 'blue', 'Invoice ' . $inv['status'],
                "Invoice {$inv['invoice_number']} for " . money($inv['total_amount']) . ' has been issued.',
                $inv['issued_date'], 'vehicleowner-invoices.php');
        }
    }

    $stmt = $pdo->prepare("
        SELECT t.stage, t.updated_at, j.id AS job_id, j.created_at AS job_created_at, v.make, v.model
          FROM RepairTimeline t
          JOIN JobCards j ON t.job_card_id = j.id
          JOIN Appointments a ON j.appointment_id = a.id
          JOIN Vehicles v ON a.vehicle_id = v.id
         WHERE a.owner_id = ?");
    $stmt->execute([$ownerId]);
    foreach ($stmt->fetchAll() as $t) {
        $code = job_code((int) $t['job_id'], $t['job_created_at']);
        $vehicle = $t['make'] . ' ' . $t['model'];
        $done = in_array($t['stage'], ['Completed', 'Ready', 'Delivered'], true);
        $add('repairs', $done ? 'fa-circle-check' : 'fa-wrench', $done ? 'navy' : 'gray',
            $done ? 'Vehicle Ready for Pickup' : 'Repair Update: ' . $t['stage'],
            $done ? "Your {$vehicle} ({$code}) has completed its service." : "Your {$vehicle} ({$code}) moved to {$t['stage']}.",
            $t['updated_at'], 'vehicleowner-repair-tracking.php?job_id=' . (int) $t['job_id']);
    }

    $stmt = $pdo->prepare("
        SELECT m.sender_id, m.message_text, m.is_read, m.sent_at, u.name
          FROM ChatMessages m JOIN Users u ON m.sender_id = u.id
         WHERE m.receiver_id = ?");
    $stmt->execute([$ownerId]);
    foreach ($stmt->fetchAll() as $m) {
        $add('messages', 'fa-comment', 'orange', 'New Message from ' . $m['name'],
            '“' . mb_strimwidth($m['message_text'], 0, 160, '…') . '”',
            $m['sent_at'], 'vehicleowner-chat.php?contact_id=' . (int) $m['sender_id'], !$m['is_read']);
    }

    foreach ($pdo->query("SELECT title, description, discount_percentage, valid_until, created_at FROM ServiceOffers WHERE status = 'Active'")->fetchAll() as $o) {
        $add('offers', 'fa-tag', 'blue', $o['title'],
            trim(($o['description'] ?? '') . ($o['valid_until'] ? ' Valid until ' . date('M d, Y', strtotime($o['valid_until'])) . '.' : '')),
            $o['created_at'], 'vehicleowner-book-appointment.php');
    }

    foreach ($pdo->query("SELECT title, content, created_at FROM ServiceBroadcasts WHERE status = 'Published' AND audience IN ('All', 'VehicleOwners')")->fetchAll() as $b) {
        $add('offers', 'fa-bullhorn', 'gray', $b['title'], $b['content'], $b['created_at'], 'vehicleowner-dashboard.php');
    }

    usort($items, fn ($a, $b) => strcmp($b['time'], $a['time']));
    return array_slice($items, 0, $limit);
}

/** Headline numbers and panels for the owner dashboard. */
function owner_dashboard(PDO $pdo, int $ownerId): array
{
    $count = function (string $sql) use ($pdo, $ownerId): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ownerId]);
        return (int) $stmt->fetchColumn();
    };

    $estimates = owner_estimates($pdo, $ownerId);
    $activeJobs = owner_jobs($pdo, $ownerId, true);

    return [
        'vehicle_count' => $count("SELECT COUNT(*) FROM Vehicles WHERE owner_id = ?"),
        'upcoming_count' => $count("SELECT COUNT(*) FROM Appointments WHERE owner_id = ? AND status IN ('Pending', 'Approved') AND preferred_date >= CURDATE()"),
        'active_repairs' => count($activeJobs),
        'pending_approvals' => count(array_filter($estimates, fn ($e) => $e['awaiting_approval'])),
        'active_job' => $activeJobs[0] ?? null,
        'awaiting_estimates' => array_values(array_filter($estimates, fn ($e) => $e['awaiting_approval'])),
        'unpaid_invoices' => array_values(array_filter(
            owner_invoices($pdo, $ownerId),
            fn ($i) => in_array($i['status'], UNPAID_INVOICE_STATUSES, true)
        )),
        'latest_invoices' => owner_invoices($pdo, $ownerId, ['limit' => 3]),
        'upcoming' => owner_upcoming_appointments($pdo, $ownerId, 3),
    ];
}
