<?php
/**
 * GET -> KPIs and job status breakdown for the logged-in mechanic.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/mechanic-data.php';

$user = require_api_role('Mechanic');
require_method(['GET']);

try {
    json_ok(mechanic_dashboard($pdo, $user['id']));
} catch (PDOException $e) {
    json_server_error($e);
}
