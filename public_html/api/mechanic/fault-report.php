<?php
/**
 * GET  [?job_id=ID] -> parsed fault reports for one assigned job, or for all assigned jobs.
 * POST { job_id, title, category, severity, description?, estimated_cost?, recommendation? }
 *      -> store the report as an AdditionalFaults row (format in mechanic-data.php).
 * Photos for a report are uploaded separately through photos.php.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/mechanic-data.php';

$user = require_api_role('Mechanic');
$mechanicId = $user['id'];
$method = require_method(['GET', 'POST']);

try {
    if ($method === 'GET') {
        $filters = [];
        if (isset($_GET['job_id'])) {
            $filters['id'] = input_id($_GET['job_id']);
            if (!$filters['id'] || !mechanic_job($pdo, $mechanicId, $filters['id'])) {
                json_error('Job not found.', 404);
            }
        }
        $result = [];
        foreach (mechanic_jobs($pdo, $mechanicId, $filters) as $job) {
            foreach (job_fault_reports($pdo, $job) as $report) {
                $result[] = $report + ['job_id' => $job['id'], 'job_code' => $job['code']];
            }
        }
        json_ok($result);
    }

    $input = read_json_body();
    $jobId = input_id($input['job_id'] ?? null);
    $job = $jobId ? mechanic_job($pdo, $mechanicId, $jobId) : null;
    if (!$job) {
        json_error('Job not found.', 404);
    }
    if (!$job['is_open']) {
        json_error('Faults can only be reported on open jobs.', 409);
    }

    $report = [
        'title' => input_text($input['title'] ?? null, 3, 120),
        'category' => in_array($input['category'] ?? null, FAULT_CATEGORIES, true) ? $input['category'] : null,
        'severity' => in_array($input['severity'] ?? null, FAULT_SEVERITIES, true) ? $input['severity'] : null,
        'description' => input_text($input['description'] ?? '', 0, 2000),
        'recommendation' => input_text($input['recommendation'] ?? '', 0, 255),
        'estimated_cost' => null,
    ];
    $errors = [];
    if ($report['title'] === null) $errors[] = 'Fault title must be 3-120 characters.';
    if ($report['category'] === null) $errors[] = 'Choose a category.';
    if ($report['severity'] === null) $errors[] = 'Choose a severity.';
    if ($report['description'] === null) $errors[] = 'Description must be 2000 characters or fewer.';
    if ($report['recommendation'] === null) $errors[] = 'Recommendation must be 255 characters or fewer.';
    // The title line is parsed back as "[Severity] Category: Title", so keep it on one line
    if ($report['title'] !== null && preg_match('/[\r\n]/', $report['title'])) $errors[] = 'Fault title must be a single line.';
    $cost = $input['estimated_cost'] ?? '';
    if ($cost !== '' && $cost !== null) {
        $cost = filter_var($cost, FILTER_VALIDATE_FLOAT);
        if ($cost === false || $cost < 0 || $cost > 10000000) {
            $errors[] = 'Estimated cost must be a number between 0 and 10,000,000.';
        } else {
            $report['estimated_cost'] = round($cost, 2);
        }
    }
    if ($errors) {
        json_error(implode(' ', $errors), 422);
    }

    $block = format_fault_report($report, $user['name']);
    $pdo->prepare("INSERT INTO AdditionalFaults (job_card_id, description) VALUES (?, ?)")->execute([$jobId, $block]);
    $faultId = (int) $pdo->lastInsertId();

    json_ok(['job_id' => $jobId, 'report' => parse_fault_report($block, '', '') + ['id' => $faultId]], 201);
} catch (PDOException $e) {
    json_server_error($e);
}
