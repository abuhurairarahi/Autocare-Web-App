<?php
/**
 * GET  ?status=open|done|<JobCards.status>&priority=Low|Normal|High&category_id=ID&sort=newest|oldest|priority
 *      -> jobs assigned to the logged-in mechanic.
 * GET  ?id=ID -> one assigned job with timeline, parts, labor and photo count.
 * POST { action: 'update_status', job_id, status }
 *      -> move an assigned job to Diagnosis / Awaiting Parts / Repairing / Testing / Completed.
 * POST { action: 'notify_manager', job_id, topic: 'estimate'|'parts_labor' }
 *      -> send the job's manager a chat message.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/mechanic-data.php';

$user = require_api_role('Mechanic');
$mechanicId = $user['id'];
$method = require_method(['GET', 'POST']);

const JOB_PRIORITIES = ['Low', 'Normal', 'High'];
const JOB_SORTS = ['newest', 'oldest', 'priority'];

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $jobId = input_id($_GET['id']);
            $job = $jobId ? mechanic_job_detail($pdo, $mechanicId, $jobId) : null;
            if (!$job) {
                json_error('Job not found.', 404);
            }
            json_ok($job);
        }

        $filters = [];
        $status = $_GET['status'] ?? '';
        if ($status !== '') {
            if (!in_array($status, ['open', 'done'], true) && !isset(MECHANIC_STATUS_FLOW[$status])) {
                json_error('Unknown status filter.', 422);
            }
            $filters['status'] = $status;
        }
        if (!empty($_GET['priority'])) {
            if (!in_array($_GET['priority'], JOB_PRIORITIES, true)) {
                json_error('Unknown priority filter.', 422);
            }
            $filters['priority'] = $_GET['priority'];
        }
        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = input_id($_GET['category_id']);
            if (!$filters['category_id']) {
                json_error('Invalid category filter.', 422);
            }
        }
        if (!empty($_GET['sort'])) {
            if (!in_array($_GET['sort'], JOB_SORTS, true)) {
                json_error('Unknown sort order.', 422);
            }
            $filters['sort'] = $_GET['sort'];
        }
        json_ok(mechanic_jobs($pdo, $mechanicId, $filters));
    }

    $input = read_json_body();
    $action = $input['action'] ?? '';
    $jobId = input_id($input['job_id'] ?? null);
    if (!$jobId) {
        json_error('job_id is required.', 422);
    }
    $job = mechanic_job($pdo, $mechanicId, $jobId);
    if (!$job) {
        json_error('Job not found.', 404);
    }

    if ($action === 'update_status') {
        $status = $input['status'] ?? '';
        if (!is_string($status) || !isset(MECHANIC_STATUS_FLOW[$status])) {
            json_error('Status must be one of: ' . implode(', ', array_keys(MECHANIC_STATUS_FLOW)) . '.', 422);
        }
        if (!$job['is_open']) {
            json_error('This job is already ' . $job['status'] . ' and can no longer be changed.', 409);
        }
        if ($status === $job['status']) {
            json_ok(['job_id' => $jobId, 'status' => $status, 'changed' => false]);
        }
        $flow = MECHANIC_STATUS_FLOW[$status];

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            UPDATE JobCards
               SET status = ?, kanban_stage = ?, progress_percentage = ?,
                   completion_date = IF(? = 'Completed', NOW(), NULL)
             WHERE job_id = ? AND mechanic_id = ?
        ");
        $stmt->execute([$status, $flow['kanban'], $flow['progress'], $status, $jobId, $mechanicId]);

        $pdo->prepare("INSERT INTO RepairTimeline (job_id, stage, updated_by) VALUES (?, ?, ?)")
            ->execute([$jobId, $status, $mechanicId]);

        // Manager pages print ActivityLogs text/subtext as HTML, so escape everything interpolated
        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")->execute([
            'Job Card <strong>' . e($job['code']) . '</strong> moved to ' . e($status) . ' by ' . e($user['name']) . '.',
            e($job['vehicle'] ?? ''),
        ]);
        $pdo->commit();

        json_ok([
            'job_id' => $jobId,
            'status' => $status,
            'kanban_stage' => $flow['kanban'],
            'progress_percentage' => $flow['progress'],
            'changed' => true,
        ]);
    }

    if ($action === 'notify_manager') {
        $topic = $input['topic'] ?? '';
        if (!$job['manager_id']) {
            json_error('No manager is assigned to this job.', 409);
        }
        if ($topic === 'estimate') {
            $text = "Diagnosis is done on {$job['code']}. Ready for a cost estimate.";
        } elseif ($topic === 'parts_labor') {
            $parts = job_parts($pdo, $jobId);
            $labor = job_labor($pdo, $jobId);
            $partsTotal = array_sum(array_column($parts, 'total_price'));
            $hours = array_sum(array_column($labor, 'hours'));
            $text = sprintf(
                'Parts & labor updated on %s: %d part line(s) totalling %s, %s labor hour(s).',
                $job['code'], count($parts), money($partsTotal), rtrim(rtrim(number_format($hours, 2), '0'), '.')
            );
        } else {
            json_error("topic must be 'estimate' or 'parts_labor'.", 422);
        }

        $pdo->prepare("INSERT INTO ChatMessages (sender_id, receiver_id, job_tag, message_text) VALUES (?, ?, ?, ?)")
            ->execute([$mechanicId, $job['manager_id'], $job['code'], $text]);

        json_ok(['sent_to' => $job['manager_name'], 'message' => $text], 201);
    }

    json_error('Unknown action.', 422);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_server_error($e);
}
