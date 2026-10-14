<?php
/**
 * GET                              -> notification feed for the logged-in owner (see owner_notifications()).
 * POST { action: 'mark_all_read' } -> mark every chat message sent to the owner as read
 *                                     (the only notification source with a read flag).
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
$method = require_method(['GET', 'POST']);

try {
    if ($method === 'GET') {
        json_ok(owner_notifications($pdo, $user['id']));
    }

    $input = read_json_body();
    if (($input['action'] ?? '') !== 'mark_all_read') {
        json_error('Unknown action.', 422);
    }
    $stmt = $pdo->prepare("UPDATE ChatMessages SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    json_ok(['marked' => $stmt->rowCount()]);
} catch (PDOException $e) {
    json_server_error($e);
}
