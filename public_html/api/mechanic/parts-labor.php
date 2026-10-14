<?php
/**
 * GET  ?job_id=ID -> parts, labor, summary totals and the spare parts catalogue for an assigned job.
 * POST { action: 'add_part', job_id, part_id, quantity }      -> request a part (Pending Approval).
 * POST { action: 'remove_part', job_part_id }                -> withdraw a part still Pending Approval.
 * POST { action: 'add_labor', job_id, start_time: 'HH:MM', end_time: 'HH:MM', description }
 * POST { action: 'remove_labor', labor_id }
 * Changes are only allowed on open jobs assigned to the logged-in mechanic.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/mechanic-data.php';

$user = require_api_role('Mechanic');
$mechanicId = $user['id'];
$method = require_method(['GET', 'POST']);

/** Load an assigned job that may still be edited, or stop with 404/409. */
function editable_job(PDO $pdo, int $mechanicId, ?int $jobId): array
{
    $job = $jobId ? mechanic_job($pdo, $mechanicId, $jobId) : null;
    if (!$job) {
        json_error('Job not found.', 404);
    }
    if (!$job['is_open']) {
        json_error('This job is ' . $job['status'] . '; parts and labor can no longer be changed.', 409);
    }
    return $job;
}

try {
    if ($method === 'GET') {
        $jobId = input_id($_GET['job_id'] ?? null);
        $job = $jobId ? mechanic_job($pdo, $mechanicId, $jobId) : null;
        if (!$job) {
            json_error('Job not found.', 404);
        }
        $parts = job_parts($pdo, $jobId);
        $labor = job_labor($pdo, $jobId);
        json_ok([
            'job' => $job,
            'parts' => $parts,
            'labor' => $labor,
            'summary' => parts_labor_summary($parts, $labor),
            'catalog' => spare_parts_catalog($pdo),
        ]);
    }

    $input = read_json_body();
    $action = $input['action'] ?? '';

    if ($action === 'add_part') {
        $job = editable_job($pdo, $mechanicId, input_id($input['job_id'] ?? null));
        $partId = input_id($input['part_id'] ?? null);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if (!$partId || $quantity === false) {
            json_error('Choose a part and a quantity between 1 and 100.', 422);
        }

        $stmt = $pdo->prepare("SELECT name, price, stock_quantity FROM SpareParts WHERE part_id = ?");
        $stmt->execute([$partId]);
        $part = $stmt->fetch();
        if (!$part) {
            json_error('Part not found.', 404);
        }
        if ($quantity > (int) $part['stock_quantity']) {
            json_error("Only {$part['stock_quantity']} of this part in stock.", 409);
        }

        $unit = (float) $part['price'];
        $pdo->prepare("INSERT INTO JobParts (job_id, part_id, quantity, unit_price, total_price, status) VALUES (?, ?, ?, ?, ?, 'Pending Approval')")
            ->execute([$job['id'], $partId, $quantity, $unit, round($unit * $quantity, 2)]);
        $newId = (int) $pdo->lastInsertId();

        // Manager pages print ActivityLogs text/subtext as HTML, so escape everything interpolated
        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'red', ?)")->execute([
            'Spare part requested: <strong>' . e($part['name']) . '</strong> x' . $quantity . ' for ' . e($job['code']) . '.',
            e(money($unit * $quantity)),
        ]);

        json_ok(['id' => $newId, 'status' => 'Pending Approval'], 201);
    }

    if ($action === 'remove_part') {
        $jobPartId = input_id($input['job_part_id'] ?? null);
        $stmt = $pdo->prepare("SELECT jp.job_id, jp.status FROM JobParts jp JOIN JobCards j ON jp.job_id = j.job_id WHERE jp.job_part_id = ? AND j.mechanic_id = ?");
        $stmt->execute([$jobPartId ?: 0, $mechanicId]);
        $row = $stmt->fetch();
        if (!$row) {
            json_error('Part request not found.', 404);
        }
        editable_job($pdo, $mechanicId, (int) $row['job_id']);
        if ($row['status'] !== 'Pending Approval') {
            json_error('Only parts still pending approval can be removed.', 409);
        }
        $pdo->prepare("DELETE FROM JobParts WHERE job_part_id = ? AND status = 'Pending Approval'")->execute([$jobPartId]);
        json_ok(['id' => $jobPartId, 'removed' => true]);
    }

    if ($action === 'add_labor') {
        $job = editable_job($pdo, $mechanicId, input_id($input['job_id'] ?? null));
        $description = input_text($input['description'] ?? null, 3, 255);
        $start = DateTime::createFromFormat('!H:i', is_string($input['start_time'] ?? null) ? $input['start_time'] : '');
        $end = DateTime::createFromFormat('!H:i', is_string($input['end_time'] ?? null) ? $input['end_time'] : '');

        $errors = [];
        if ($description === null) $errors[] = 'Task description must be 3-255 characters.';
        if (!$start || !$end) {
            $errors[] = 'Start and end time must be HH:MM.';
        } elseif ($end <= $start) {
            $errors[] = 'End time must be after start time.';
        }
        if ($errors) {
            json_error(implode(' ', $errors), 422);
        }
        $hours = round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);

        $pdo->prepare("INSERT INTO JobLabor (job_id, description, hours, hourly_rate) VALUES (?, ?, ?, ?)")
            ->execute([$job['id'], $description, $hours, DEFAULT_HOURLY_RATE]);

        json_ok(['id' => (int) $pdo->lastInsertId(), 'hours' => $hours, 'hourly_rate' => DEFAULT_HOURLY_RATE], 201);
    }

    if ($action === 'remove_labor') {
        $laborId = input_id($input['labor_id'] ?? null);
        $stmt = $pdo->prepare("SELECT l.job_id FROM JobLabor l JOIN JobCards j ON l.job_id = j.job_id WHERE l.labor_id = ? AND j.mechanic_id = ?");
        $stmt->execute([$laborId ?: 0, $mechanicId]);
        $jobId = $stmt->fetchColumn();
        if (!$jobId) {
            json_error('Labor entry not found.', 404);
        }
        editable_job($pdo, $mechanicId, (int) $jobId);
        $pdo->prepare("DELETE FROM JobLabor WHERE labor_id = ?")->execute([$laborId]);
        json_ok(['id' => $laborId, 'removed' => true]);
    }

    json_error('Unknown action.', 422);
} catch (PDOException $e) {
    json_server_error($e);
}
