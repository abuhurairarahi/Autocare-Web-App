<?php
/**
 * GET              -> the owner's active repairs (summary list).
 * GET ?job_id=ID   -> one of the owner's jobs with timeline, photos and approved total.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
require_method(['GET']);

try {
    if (isset($_GET['job_id'])) {
        $jobId = input_id($_GET['job_id']);
        $job = $jobId ? owner_job_detail($pdo, $user['id'], $jobId) : null;
        if (!$job) {
            json_error('Repair not found.', 404);
        }
        json_ok($job);
    }
    json_ok(owner_jobs($pdo, $user['id'], true));
} catch (PDOException $e) {
    json_server_error($e);
}
