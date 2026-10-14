<?php
/**
 * GET  -> booking form options (own vehicles, workshops, service categories)
 *         and the owner's upcoming appointments.
 * POST -> book an appointment for one of the owner's vehicles.
 *         { vehicle_id, workshop_id, service_category_id, preferred_date: 'YYYY-MM-DD',
 *           time_slot: 'morning'|'afternoon'|'evening', description? }
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/owner-data.php';

$user = require_api_role('VehicleOwner');
$ownerId = $user['id'];
$method = require_method(['GET', 'POST']);

try {
    if ($method === 'GET') {
        json_ok([
            'vehicles' => owner_vehicles($pdo, $ownerId),
            'workshops' => booking_workshops($pdo),
            'categories' => booking_categories($pdo),
            'upcoming' => owner_upcoming_appointments($pdo, $ownerId, 10),
        ]);
    }

    $input = read_json_body();
    $vehicleId = input_id($input['vehicle_id'] ?? null);
    $workshopId = input_id($input['workshop_id'] ?? null);
    $categoryId = input_id($input['service_category_id'] ?? null);
    $slot = is_string($input['time_slot'] ?? null) ? $input['time_slot'] : '';
    $description = input_text($input['description'] ?? '', 0, 2000);
    $date = DateTime::createFromFormat('!Y-m-d', is_string($input['preferred_date'] ?? null) ? $input['preferred_date'] : '');

    $errors = [];
    if (!$vehicleId) $errors[] = 'Select a vehicle.';
    if (!$workshopId) $errors[] = 'Select a workshop.';
    if (!$categoryId) $errors[] = 'Select a service type.';
    if (!isset(TIME_SLOTS[$slot])) $errors[] = 'Select a preferred time.';
    if ($description === null) $errors[] = 'Additional details must be 2000 characters or fewer.';
    $today = new DateTime(date('Y-m-d', now_ts()));
    if (!$date || $date->format('Y-m-d') !== $input['preferred_date']) {
        $errors[] = 'Choose a valid preferred date.';
    } elseif ($date < $today || $date > (clone $today)->modify('+6 months')) {
        $errors[] = 'Preferred date must be between today and 6 months from now.';
    }
    if ($errors) {
        json_error(implode(' ', $errors), 422);
    }

    // The vehicle must belong to the logged-in owner
    $stmt = $pdo->prepare("SELECT make, model FROM Vehicles WHERE vehicle_id = ? AND owner_id = ?");
    $stmt->execute([$vehicleId, $ownerId]);
    $vehicle = $stmt->fetch();
    if (!$vehicle) {
        json_error('Vehicle not found.', 404);
    }

    $stmt = $pdo->prepare("SELECT 1 FROM Workshops WHERE workshop_id = ?");
    $stmt->execute([$workshopId]);
    if (!$stmt->fetchColumn()) {
        json_error('Workshop not found.', 404);
    }

    $stmt = $pdo->prepare("SELECT name FROM ServiceCategories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $categoryName = $stmt->fetchColumn();
    if ($categoryName === false) {
        json_error('Service type not found.', 404);
    }

    $preferred = $date->format('Y-m-d') . ' ' . TIME_SLOTS[$slot][1];

    $stmt = $pdo->prepare("
        SELECT 1 FROM Appointments
         WHERE owner_id = ? AND vehicle_id = ? AND DATE(preferred_date) = ? AND status IN ('Pending', 'Approved')
    ");
    $stmt->execute([$ownerId, $vehicleId, $date->format('Y-m-d')]);
    if ($stmt->fetchColumn()) {
        json_error('This vehicle already has an appointment on that date.', 409);
    }

    // Appointments.code is UNIQUE; retry on the rare collision
    $insert = $pdo->prepare("
        INSERT INTO Appointments (code, owner_id, vehicle_id, workshop_id, service_category_id, preferred_date, issue_description, priority, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Normal', 'Pending')
    ");
    for ($attempt = 0; ; $attempt++) {
        $code = 'BRQ-' . date('Y') . '-' . random_int(1000, 99999);
        try {
            $insert->execute([$code, $ownerId, $vehicleId, $workshopId, $categoryId, $preferred, $description !== '' ? $description : null]);
            break;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $attempt >= 4) {
                throw $e;
            }
        }
    }
    $appointmentId = (int) $pdo->lastInsertId();

    // Manager pages print ActivityLogs text/subtext as HTML, so escape everything interpolated
    $pdo->prepare("INSERT INTO ActivityLogs (text, type, subtext) VALUES (?, 'blue', ?)")->execute([
        'New Booking Request <strong>' . e($code) . '</strong> submitted by ' . e($user['name']) . '.',
        e($vehicle['make'] . ' ' . $vehicle['model']),
    ]);

    json_ok([
        'id' => $appointmentId,
        'code' => $code,
        'preferred_date' => $preferred,
        'status' => 'Pending',
        'category_name' => $categoryName,
    ], 201);
} catch (PDOException $e) {
    json_server_error($e);
}
