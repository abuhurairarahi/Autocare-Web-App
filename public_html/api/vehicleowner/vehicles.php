<?php
/**
 * GET  -> the logged-in owner's vehicles with their current repair status.
 * POST -> register a new vehicle for the logged-in owner.
 *         { make, model, year, license_plate, vin? }
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
$ownerId = $user['id'];
$method = require_method(['GET', 'POST']);

try {
    if ($method === 'GET') {
        json_ok(owner_vehicles($pdo, $ownerId));
    }

    // POST: add vehicle
    $input = read_json_body();
    $make = input_text($input['make'] ?? null, 1, 50);
    $model = input_text($input['model'] ?? null, 1, 50);
    $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1950, 'max_range' => (int) date('Y') + 1],
    ]);
    $plate = input_text($input['license_plate'] ?? null, 2, 20);
    $vin = input_text($input['vin'] ?? '', 0, 50);

    $errors = [];
    if ($make === null) $errors[] = 'Make is required (max 50 characters).';
    if ($model === null) $errors[] = 'Model is required (max 50 characters).';
    if ($year === false) $errors[] = 'Year must be between 1950 and ' . ((int) date('Y') + 1) . '.';
    if ($plate === null || !preg_match('/^[A-Za-z0-9][A-Za-z0-9 \-]*$/u', $plate)) {
        $errors[] = 'License plate must be 2-20 letters, numbers, spaces or dashes.';
    }
    if ($vin === null || ($vin !== '' && !preg_match('/^[A-HJ-NPR-Z0-9]{11,17}$/i', $vin))) {
        $errors[] = 'VIN must be 11-17 letters/numbers (no I, O or Q).';
    }
    if ($errors) {
        json_error(implode(' ', $errors), 422);
    }

    $plate = strtoupper($plate);
    $vin = $vin === '' ? null : strtoupper($vin);

    // License plate and VIN are UNIQUE across all owners
    $stmt = $pdo->prepare("SELECT license_plate, vin FROM Vehicles WHERE license_plate = ? OR (vin IS NOT NULL AND vin = ?) LIMIT 1");
    $stmt->execute([$plate, $vin]);
    if ($dup = $stmt->fetch()) {
        $what = $dup['license_plate'] === $plate ? 'license plate' : 'VIN';
        json_error("A vehicle with this {$what} is already registered.", 409);
    }

    $stmt = $pdo->prepare("INSERT INTO Vehicles (owner_id, make, model, year, license_plate, vin) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$ownerId, $make, $model, $year, $plate, $vin]);
    $newId = (int) $pdo->lastInsertId();

    foreach (owner_vehicles($pdo, $ownerId) as $vehicle) {
        if ($vehicle['id'] === $newId) {
            json_ok($vehicle, 201);
        }
    }
    json_ok(['id' => $newId], 201);
} catch (PDOException $e) {
    // Race on the UNIQUE plate/VIN index
    if ($e->getCode() === '23000') {
        json_error('A vehicle with this license plate or VIN is already registered.', 409);
    }
    json_server_error($e);
}
