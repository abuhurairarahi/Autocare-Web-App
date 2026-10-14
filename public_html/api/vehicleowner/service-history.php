<?php
/**
 * GET [?vehicle_id=ID] -> the owner's finished jobs (Completed/Delivered), each with its
 *                         invoice (if any), plus summary stats.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
require_method(['GET']);

try {
    $vehicleId = null;
    if (!empty($_GET['vehicle_id'])) {
        $vehicleId = input_id($_GET['vehicle_id']);
        if (!$vehicleId) {
            json_error('Invalid vehicle filter.', 422);
        }
    }
    json_ok(owner_service_history($pdo, $user['id'], $vehicleId));
} catch (PDOException $e) {
    json_server_error($e);
}
