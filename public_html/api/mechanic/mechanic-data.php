<?php
/**
 * mechanic-data.php
 * Read queries for the Mechanic panel, shared by the server-rendered pages and
 * the JSON endpoints. Every function is scoped to the given $mechanicId, which
 * callers must take from the session.
 */

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../repair-stages.php';

// JobLabor.hourly_rate is NOT NULL but the schema has no rate table; this is the shop default
const DEFAULT_HOURLY_RATE = 150.00;

const OPEN_JOB_SQL = "j.status NOT IN ('Completed', 'Delivered')";

/**
 * Jobs assigned to the mechanic. Filters: status ('open' | 'done' | a JobCards.status),
 * priority (Low/Normal/High), category_id, sort ('newest' | 'oldest' | 'priority'), id.
 */
function mechanic_jobs(PDO $pdo, int $mechanicId, array $filters = []): array
{
    $sql = "
        SELECT j.job_id AS id,
               COALESCE(j.code, CONCAT('JC-', j.job_id)) AS code,
               j.work_order, j.status, j.kanban_stage, j.progress_percentage,
               COALESCE(j.service_text, j.fault_report, 'General Service') AS service_text,
               j.fault_report, j.estimated_cost, j.delivery_date, j.start_date, j.completion_date,
               j.manager_id, mgr.name AS manager_name,
               COALESCE(a.priority, 'Normal') AS priority,
               a.issue_description,
               sc.name AS category_name,
               v.make, v.model, v.year, v.license_plate,
               o.user_id AS owner_id, o.name AS owner_name
          FROM JobCards j
          LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
          LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.category_id
          LEFT JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
          LEFT JOIN Users o ON a.owner_id = o.user_id
          LEFT JOIN Users mgr ON j.manager_id = mgr.user_id
         WHERE j.mechanic_id = ?
    ";
    $params = [$mechanicId];

    $status = $filters['status'] ?? null;
    if ($status === 'open') {
        $sql .= " AND " . OPEN_JOB_SQL;
    } elseif ($status === 'done') {
        $sql .= " AND j.status IN ('Completed', 'Delivered')";
    } elseif ($status) {
        $sql .= " AND j.status = ?";
        $params[] = $status;
    }
    if (!empty($filters['priority'])) {
        $sql .= " AND COALESCE(a.priority, 'Normal') = ?";
        $params[] = $filters['priority'];
    }
    if (!empty($filters['category_id'])) {
        $sql .= " AND a.service_category_id = ?";
        $params[] = (int) $filters['category_id'];
    }
    if (!empty($filters['id'])) {
        $sql .= " AND j.job_id = ?";
        $params[] = (int) $filters['id'];
    }

    $order = [
        'oldest' => 'j.start_date ASC, j.job_id ASC',
        'priority' => "FIELD(COALESCE(a.priority, 'Normal'), 'High', 'Normal', 'Low'), j.start_date DESC",
    ][$filters['sort'] ?? ''] ?? 'j.start_date DESC, j.job_id DESC';
    $sql .= " ORDER BY {$order}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['estimated_cost'] = (float) $row['estimated_cost'];
        $row['progress_percentage'] = (int) $row['progress_percentage'];
        $row['is_open'] = !in_array($row['status'], ['Completed', 'Delivered'], true);
        $row['vehicle'] = $row['make'] ? trim($row['year'] . ' ' . $row['make'] . ' ' . $row['model']) : null;
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
          FROM RepairTimeline t LEFT JOIN Users u ON t.updated_by = u.user_id
         WHERE t.job_id = ?
         ORDER BY t.updated_at DESC, t.timeline_id DESC
    ");
    $stmt->execute([$jobId]);
    $job['timeline'] = $stmt->fetchAll();

    $job['parts'] = job_parts($pdo, $jobId);
    $job['labor'] = job_labor($pdo, $jobId);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM RepairPhotos WHERE job_id = ?");
    $stmt->execute([$jobId]);
    $job['photo_count'] = (int) $stmt->fetchColumn();

    return $job;
}

function job_parts(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare("
        SELECT jp.job_part_id AS id, jp.part_id, sp.name, sp.sku, jp.quantity, jp.unit_price,
               jp.total_price, jp.status, jp.rejection_reason, jp.requested_at
          FROM JobParts jp JOIN SpareParts sp ON jp.part_id = sp.part_id
         WHERE jp.job_id = ?
         ORDER BY jp.job_part_id
    ");
    $stmt->execute([$jobId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['quantity'] = (int) $row['quantity'];
        $row['unit_price'] = (float) $row['unit_price'];
        $row['total_price'] = (float) $row['total_price'];
    }
    return $rows;
}

function job_labor(PDO $pdo, int $jobId): array
{
    $stmt = $pdo->prepare("SELECT labor_id AS id, description, hours, hourly_rate FROM JobLabor WHERE job_id = ? ORDER BY labor_id");
    $stmt->execute([$jobId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
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
    $stmt = $pdo->prepare("SELECT photo_id AS id, photo_url, description, uploaded_at FROM RepairPhotos WHERE job_id = ? ORDER BY uploaded_at DESC, photo_id DESC");
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

// Fault reports are appended to JobCards.fault_report as text blocks:
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
        // Blank lines separate reports, so they cannot appear inside one
        $lines[] = preg_replace("/\n\\s*\n/", "\n", $r['description']);
    }
    if ($r['recommendation'] !== '') $lines[] = 'Recommendation: ' . $r['recommendation'];
    if ($r['estimated_cost'] !== null) $lines[] = 'Estimated cost: ' . money($r['estimated_cost']);
    $lines[] = 'Reported by ' . $reporter . ' on ' . date('Y-m-d H:i', now_ts());
    return implode("\n", $lines);
}

/**
 * Split a job's fault_report into report cards, newest first. Text that is not in the
 * block format (e.g. seed data or manager notes) becomes a single untitled card.
 */
function parse_fault_reports(?string $text): array
{
    $text = trim((string) $text);
    if ($text === '') {
        return [];
    }
    $reports = [];
    foreach (preg_split("/\n\\s*\n/", str_replace("\r\n", "\n", $text)) as $block) {
        $lines = explode("\n", trim($block));
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
            $reports[] = $report;
        } else {
            $reports[] = ['severity' => null, 'category' => 'Job notes', 'title' => 'Inspection notes', 'body' => trim($block), 'reported' => null];
        }
    }
    return array_reverse($reports);
}

/** Spare parts catalogue for the "Add Part" picker. */
function spare_parts_catalog(PDO $pdo): array
{
    return $pdo->query("SELECT part_id AS id, sku, name, category, price, stock_quantity, unit FROM SpareParts ORDER BY name")->fetchAll();
}

/** KPIs and status breakdown for the mechanic dashboard. */
function mechanic_dashboard(PDO $pdo, int $mechanicId): array
{
    $one = function (string $sql) use ($pdo, $mechanicId) {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$mechanicId]);
        return $stmt->fetchColumn();
    };

    $stmt = $pdo->prepare("SELECT j.status, COUNT(*) AS n FROM JobCards j WHERE j.mechanic_id = ? AND " . OPEN_JOB_SQL . " GROUP BY j.status");
    $stmt->execute([$mechanicId]);
    $byStatus = array_map('intval', $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

    $inProgress = ($byStatus['In Progress'] ?? 0) + ($byStatus['Repairing'] ?? 0) + ($byStatus['Awaiting Parts'] ?? 0);
    $testing = $byStatus['Testing'] ?? 0;
    $open = array_sum($byStatus);

    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(jp.quantity), 0) AS qty, COUNT(DISTINCT jp.job_id) AS jobs
          FROM JobParts jp JOIN JobCards j ON jp.job_id = j.job_id
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
        'open_labor_hours' => (float) $one("SELECT COALESCE(SUM(l.hours), 0) FROM JobLabor l JOIN JobCards j ON l.job_id = j.job_id WHERE j.mechanic_id = ? AND " . OPEN_JOB_SQL),
        'parts_today' => (int) $partsToday['qty'],
        'parts_today_jobs' => (int) $partsToday['jobs'],
        'by_status' => $byStatus,
    ];
}
