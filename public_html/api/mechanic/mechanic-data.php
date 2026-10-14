<?php
/**
 * mechanic-data.php
 * Read queries for the Mechanic panel, shared by the server-rendered pages and
 * the JSON endpoints. Every function is scoped to the given $mechanicId (a Users.id),
 * which callers must take from the session.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../repair-stages.php';

// JobCardLabor.hourly_rate is NOT NULL but the schema has no rate table; this is the shop default
const DEFAULT_HOURLY_RATE = 150.00;

const OPEN_JOB_SQL = "j.status NOT IN ('Completed', 'Delivered')";

// Appointments have no priority column; like the manager booking API, every job is 'Normal'
const DEFAULT_PRIORITY = 'Normal';

/**
 * Jobs assigned to the mechanic. Filters: status ('open' | 'done' | a displayed status),
 * priority (Low/Normal/High), category_id, sort ('newest' | 'oldest' | 'priority'), id.
 */
function mechanic_jobs(PDO $pdo, int $mechanicId, array $filters = []): array
{
    $sql = "
        SELECT j.id, j.created_at, j.status AS db_status, " . display_status_sql('j') . " AS shown_status,
               j.fault_report, j.estimated_cost, j.delivery_date, j.start_date, j.completion_date,
               j.manager_id, mgr.name AS manager_name,
               a.issue_description, a.service_category_id,
               sc.name AS category_name,
               v.make, v.model, v.year, v.license_plate,
               o.id AS owner_id, o.name AS owner_name
          FROM JobCards j
          LEFT JOIN Appointments a ON j.appointment_id = a.id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.id
          LEFT JOIN Vehicles v ON a.vehicle_id = v.id
          LEFT JOIN Users o ON a.owner_id = o.id
          LEFT JOIN Users mgr ON j.manager_id = mgr.id
         WHERE j.mechanic_id = ?
    ";
    $params = [$mechanicId];

    $status = $filters['status'] ?? null;
    if ($status === 'open') {
        $sql .= " AND " . OPEN_JOB_SQL;
    } elseif ($status === 'done') {
        $sql .= " AND j.status IN ('Completed', 'Delivered')";
    }
    if (!empty($filters['priority']) && $filters['priority'] !== DEFAULT_PRIORITY) {
        $sql .= " AND 1 = 0";
    }
    if (!empty($filters['category_id'])) {
        $sql .= " AND a.service_category_id = ?";
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['id'])) {
        $sql .= " AND j.id = ?";
        $params[] = (int) $filters['id'];
    }
    $sql .= ($filters['sort'] ?? '') === 'oldest'
        ? " ORDER BY COALESCE(j.start_date, j.created_at) ASC, j.id ASC"
        : " ORDER BY COALESCE(j.start_date, j.created_at) DESC, j.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['id'] = (int) $row['id'];
        $row['status'] = display_status($row['db_status'], $row['shown_status']);
        unset($row['shown_status']);
        if ($status && !in_array($status, ['open', 'done'], true) && $row['status'] !== $status) {
            continue;
        }
        $progress = status_progress($row['status']);
        $row['code'] = job_code($row['id'], $row['created_at']);
        $row['work_order'] = null;
        $row['kanban_stage'] = $progress['kanban'];
        $row['progress_percentage'] = $progress['progress'];
        $row['service_text'] = $row['category_name'] ?: ($row['issue_description'] ?: ($row['fault_report'] ?: 'General Service'));
        $row['priority'] = DEFAULT_PRIORITY;
        $row['estimated_cost'] = (float) $row['estimated_cost'];
        $row['is_open'] = !in_array($row['db_status'], ['Completed', 'Delivered'], true);
        $row['vehicle'] = $row['make'] ? trim($row['year'] . ' ' . $row['make'] . ' ' . $row['model']) : null;
        $rows[] = $row;
    }
    return $rows;
}

/** One assigned job, or null when it does not exist or is assigned to someone else. */
function mechanic_job(PDO $pdo, int $mechanicId, int $jobId): ?array
{
    $rows = mechanic_jobs($pdo, $mechanicId, ['id' => $jobId]);
    return $rows[0] ?? null;
}

/** Assigned job with timeline, parts, labor and photo count. */
function mechanic_job_detail(PDO $pdo, int $mechanicId, int $jobId): ?array
{
    $job = mechanic_job($pdo, $mechanicId, $jobId);
    if (!$job) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT t.stage, t.updated_at, u.name AS updated_by_name
          FROM RepairTimeline t LEFT JOIN Users u ON t.updated_by = u.id
         WHERE t.job_card_id = ?
         ORDER BY t.updated_at DESC, t.id DESC
    ");
    $stmt->execute([$jobId]);
    $job['timeline'] = $stmt->fetchAll();

    $job['parts'] = job_parts($pdo, $jobId);
    $job['labor'] = job_labor($pdo, $jobId);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM JobCardPhotos WHERE job_card_id = ?");
    $stmt->execute([$jobId]);
    $job['photo_count'] = (int) $stmt->fetchColumn();

    return $job;
}

function job_parts(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare("
        SELECT jp.id, jp.part_id, sp.name, sp.sku, jp.quantity, jp.unit_price,
               ROUND(jp.quantity * jp.unit_price, 2) AS total_price, jp.status, jp.requested_at
          FROM JobCardParts jp JOIN SpareParts sp ON jp.part_id = sp.id
         WHERE jp.job_card_id = ?
         ORDER BY jp.id
    ");
    $stmt->execute([$jobId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['quantity'] = (int) $row['quantity'];
        $row['unit_price'] = (float) $row['unit_price'];
        $row['total_price'] = (float) $row['total_price'];
        $row['rejection_reason'] = null; // not stored in this schema
    }
    return $rows;
}

function job_labor(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare("SELECT id, mechanic_id, description, hours, hourly_rate, logged_at FROM JobCardLabor WHERE job_card_id = ? ORDER BY id");
    $stmt->execute([$jobId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['mechanic_id'] = (int) $row['mechanic_id'];
        $row['hours'] = (float) $row['hours'];
        $row['hourly_rate'] = (float) $row['hourly_rate'];
        $row['total'] = round($row['hours'] * $row['hourly_rate'], 2);
    }
    return $rows;
}

/** Totals shown in the page's Summary panel. */
function parts_labor_summary(array $parts, array $labor): array
{
    $partsCost = 0.0;
    foreach ($parts as $p) {
        if ($p['status'] !== 'Rejected') {
            $partsCost += $p['total_price'];
        }
    }
    $hours = array_sum(array_column($labor, 'hours'));
    $laborCost = array_sum(array_column($labor, 'total'));
    return [
        'parts_cost' => round($partsCost, 2),
        'labor_hours' => round($hours, 2),
        'labor_rate' => $hours > 0 ? round($laborCost / $hours, 2) : DEFAULT_HOURLY_RATE,
        'labor_cost' => round($laborCost, 2),
        'estimated_total' => round($partsCost + $laborCost, 2),
    ];
}

/** Photos for a job, newest first, with unsafe URLs dropped. */
function job_photos(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare("SELECT id, photo_url, type, description, uploaded_at FROM JobCardPhotos WHERE job_card_id = ? ORDER BY uploaded_at DESC, id DESC");
    $stmt->execute([$jobId]);
    $photos = [];
    foreach ($stmt->fetchAll() as $p) {
        $p['id'] = (int) $p['id'];
        $p['photo_url'] = safe_image_url($p['photo_url']);
        if ($p['photo_url'] !== '') {
            $photos[] = $p;
        }
    }
    return $photos;
}

// Each fault report is one AdditionalFaults row whose description is a text block:
//   [High] Engine: Oil leak at valve cover
//   <description>
//   Recommendation: <text>
//   Estimated cost: <amount>
//   Reported by <name> on <Y-m-d H:i>
const FAULT_CATEGORIES = ['Engine', 'Braking System', 'Bodywork', 'Electrical', 'Transmission'];
const FAULT_SEVERITIES = ['Low', 'Medium', 'High'];

function format_fault_report(array $r, string $reporter): string
{
    $lines = ["[{$r['severity']}] {$r['category']}: {$r['title']}"];
    if ($r['description'] !== '') {
        $lines[] = $r['description'];
    }
    if ($r['recommendation'] !== '') $lines[] = 'Recommendation: ' . $r['recommendation'];
    if ($r['estimated_cost'] !== null) $lines[] = 'Estimated cost: ' . money($r['estimated_cost']);
    $lines[] = 'Reported by ' . $reporter . ' on ' . date('Y-m-d H:i', now_ts());
    return implode("\n", $lines);
}

/**
 * Parse one report block. Text not in the block format (e.g. faults added by the
 * manager, or the job card's own fault_report) becomes an untitled card.
 */
function parse_fault_report(string $text, string $fallbackCategory, string $fallbackTitle, ?string $reportedAt = null): array
{
    $lines = explode("\n", trim(str_replace("\r\n", "\n", $text)));
    if (preg_match('/^\[(Low|Medium|High)\] ([^:]+): (.+)$/', $lines[0], $m)) {
        $report = ['severity' => $m[1], 'category' => $m[2], 'title' => $m[3], 'body' => [], 'reported' => null];
        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^Reported by (.+) on (\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/', $line, $r)) {
                $report['reported'] = ['by' => $r[1], 'at' => $r[2]];
            } else {
                $report['body'][] = $line;
            }
        }
        $report['body'] = implode("\n", $report['body']);
        return $report;
    }
    return [
        'severity' => null, 'category' => $fallbackCategory, 'title' => $fallbackTitle, 'body' => trim($text),
        'reported' => $reportedAt ? ['by' => null, 'at' => date('Y-m-d H:i', strtotime($reportedAt))] : null,
    ];
}

/** Fault report cards for a job, newest first: AdditionalFaults rows, then the job card's fault_report notes. */
function job_fault_reports(PDO $pdo, array $job): array
{
    $stmt = $pdo->prepare("SELECT id, description, reported_at FROM AdditionalFaults WHERE job_card_id = ? ORDER BY reported_at DESC, id DESC");
    $stmt->execute([$job['id']]);
    $reports = [];
    foreach ($stmt->fetchAll() as $row) {
        $reports[] = parse_fault_report($row['description'], 'Additional fault', 'Additional fault found', $row['reported_at']) + ['id' => (int) $row['id']];
    }
    if (trim((string) $job['fault_report']) !== '') {
        $reports[] = parse_fault_report($job['fault_report'], 'Job notes', 'Inspection notes') + ['id' => null];
    }
    return $reports;
}

/** Spare parts catalogue for the "Add Part" picker. */
function spare_parts_catalog(PDO $pdo): array
{
    return $pdo->query("SELECT id, sku, name, category, price, stock_quantity, 'pcs' AS unit FROM SpareParts ORDER BY name")->fetchAll();
}

/** KPIs and status breakdown for the mechanic dashboard. */
function mechanic_dashboard(PDO $pdo, int $mechanicId): array
{
    $one = function (string $sql) use ($pdo, $mechanicId) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mechanicId]);
        return $stmt->fetchColumn();
    };

    $byStatus = [];
    foreach (mechanic_jobs($pdo, $mechanicId, ['status' => 'open']) as $job) {
        $byStatus[$job['status']] = ($byStatus[$job['status']] ?? 0) + 1;
    }

    $inProgress = ($byStatus['In Progress'] ?? 0) + ($byStatus['Repairing'] ?? 0) + ($byStatus['Awaiting Parts'] ?? 0);
    $testing = $byStatus['Testing'] ?? 0;
    $open = array_sum($byStatus);

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(jp.quantity), 0) AS qty, COUNT(DISTINCT jp.job_card_id) AS jobs
          FROM JobCardParts jp JOIN JobCards j ON jp.job_card_id = j.id
         WHERE j.mechanic_id = ? AND DATE(jp.requested_at) = CURDATE()
    ");
    $stmt->execute([$mechanicId]);
    $partsToday = $stmt->fetch();

    return [
        'assigned_jobs' => $open,
        'in_progress' => $inProgress,
        'testing' => $testing,
        'not_started' => $open - $inProgress - $testing,
        'completed_today' => (int) $one("SELECT COUNT(*) FROM JobCards WHERE mechanic_id = ? AND status IN ('Completed', 'Delivered') AND DATE(completion_date) = CURDATE()"),
        'open_labor_hours' => (float) $one("SELECT COALESCE(SUM(l.hours), 0) FROM JobCardLabor l JOIN JobCards j ON l.job_card_id = j.id WHERE j.mechanic_id = ? AND " . OPEN_JOB_SQL),
        'parts_today' => (int) $partsToday['qty'],
        'parts_today_jobs' => (int) $partsToday['jobs'],
        'by_status' => $byStatus,
    ];
}
