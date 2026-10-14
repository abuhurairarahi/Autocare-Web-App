<?php
/**
 * GET                                  -> the owner's invoices + summary stats.
 *     ?vehicle_id=ID&date=YYYY-MM-DD   -> filtered list.
 * GET ?id=ID                           -> one invoice with parts and labor line items.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
$ownerId = $user['id'];
require_method(['GET']);

try {
    if (isset($_GET['id'])) {
        $invoiceId = input_id($_GET['id']);
        $invoice = $invoiceId ? owner_invoice_detail($pdo, $ownerId, $invoiceId) : null;
        if (!$invoice) {
            json_error('Invoice not found.', 404);
        }
        json_ok($invoice);
    }

    $filters = [];
    if (!empty($_GET['vehicle_id'])) {
        $filters['vehicle_id'] = input_id($_GET['vehicle_id']);
        if (!$filters['vehicle_id']) {
            json_error('Invalid vehicle filter.', 422);
        }
    }
    if (!empty($_GET['date'])) {
        $d = DateTime::createFromFormat('!Y-m-d', (string) $_GET['date']);
        if (!$d || $d->format('Y-m-d') !== $_GET['date']) {
            json_error('Date filter must be YYYY-MM-DD.', 422);
        }
        $filters['date'] = $_GET['date'];
    }

    json_ok([
        'invoices' => owner_invoices($pdo, $ownerId, $filters),
        'stats' => owner_invoice_stats($pdo, $ownerId),
    ]);
} catch (PDOException $e) {
    json_server_error($e);
}
