<?php
/**
 * GET  -> estimates sent to the logged-in owner (Drafts are never shown).
 * POST { action: 'approve', estimate_id }          -> approve an estimate awaiting approval.
 * POST { action: 'clarify', estimate_id, message } -> ask the job's manager about an estimate (chat message).
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
$ownerId = $user['id'];
$method = require_method(['GET', 'POST']);

try {
    if ($method === 'GET') {
        json_ok(owner_estimates($pdo, $ownerId));
    }

    $input = read_json_body();
    $action = $input['action'] ?? '';
    $estimateId = input_id($input['estimate_id'] ?? null);
    $estimate = $estimateId ? (owner_estimates($pdo, $ownerId, $estimateId)[0] ?? null) : null;
    if (!$estimate) {
        json_error('Estimate not found.', 404);
    }

    if ($action === 'approve') {
        if (!$estimate['awaiting_approval']) {
            json_error('This estimate is ' . $estimate['status'] . ' and cannot be approved.', 409);
        }
        $stmt = $pdo->prepare("UPDATE RepairEstimates SET status = 'Approved' WHERE estimate_id = ? AND status IN ('Sent', 'Send to Customer')");
        $stmt->execute([$estimateId]);
        if ($stmt->rowCount() !== 1) {
            json_error('This estimate was changed by someone else. Please reload.', 409);
        }

        // Manager pages print ActivityLogs text/subtext as HTML, so escape everything interpolated
        $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")->execute([
            'Estimate <strong>' . e($estimate['code']) . '</strong> approved by ' . e($user['name']) . '.',
            e(money($estimate['total_estimated_cost'])),
        ]);
        json_ok(['id' => $estimateId, 'status' => 'Approved']);
    }

    if ($action === 'clarify') {
        $message = input_text($input['message'] ?? null, 3, 1000);
        if ($message === null) {
            json_error('Write a question of 3-1000 characters.', 422);
        }
        $stmt = $pdo->prepare("SELECT manager_id FROM JobCards WHERE job_id = ?");
        $stmt->execute([$estimate['job_id']]);
        $managerId = (int) $stmt->fetchColumn();
        if (!$managerId) {
            json_error('No manager is assigned to this job yet.', 409);
        }
        $pdo->prepare("INSERT INTO ChatMessages (sender_id, receiver_id, job_tag, message_text) VALUES (?, ?, ?, ?)")
            ->execute([$ownerId, $managerId, $estimate['job_code'], "Question about estimate {$estimate['code']}: {$message}"]);
        json_ok(['sent' => true], 201);
    }

    json_error('Unknown action.', 422);
} catch (PDOException $e) {
    json_server_error($e);
}
