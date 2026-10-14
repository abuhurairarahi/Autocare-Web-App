<?php
/**
 * GET -> dashboard KPIs, the most recent active repair, items needing action,
 *        latest invoices and upcoming appointments for the logged-in owner.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
require_method(['GET']);

try {
    json_ok(owner_dashboard($pdo, $user['id']));
} catch (PDOException $e) {
    json_server_error($e);
}
