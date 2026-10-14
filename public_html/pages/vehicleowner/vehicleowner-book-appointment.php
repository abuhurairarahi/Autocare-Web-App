<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$vehicles = owner_vehicles($pdo, $ownerId);
$workshops = booking_workshops($pdo);
$categories = booking_categories($pdo);
$upcoming = owner_upcoming_appointments($pdo, $ownerId, 3);
$selectedVehicle = (int) ($_GET['vehicle_id'] ?? 0);

// Icon per service category, picked by keyword in the category name
function category_icon(string $name): string
{
    $map = [
        'engine' => 'fa-gears', 'transmission' => 'fa-gear', 'brake' => 'fa-circle-stop',
        'electric' => 'fa-bolt', 'maintenance' => 'fa-screwdriver-wrench', 'tire' => 'fa-bullseye',
        'ac' => 'fa-snowflake', 'paint' => 'fa-spray-can',
    ];
    foreach ($map as $keyword => $icon) {
        if (stripos($name, $keyword) !== false) {
            return $icon;
        }
    }
    return 'fa-triangle-exclamation';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Book Service Appointment</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-book-appointment.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/vehicleowner-sidebar-topbar-style.css">
</head>

<body>
    <div class="app">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-brand">
                <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
                <span class="brand-name"><span style="color: #00175c;">Auto</span><span
                        style="color: #fc4204;">Care</span></span>
            </div>

            <!-- Nav Bar -->
            <nav class="nav">
                <a href="vehicleowner-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="vehicleowner-vehicles.php" class="nav-item"><i class="fa-solid fa-car"></i> My Vehicles</a>
                <a href="vehicleowner-book-appointment.php" class="nav-item active"><i class="fa-solid fa-calendar-check"></i> Service Appointments</a>
                <a href="vehicleowner-repair-estimates.php" class="nav-item"><i class="fa-solid fa-file-invoice-dollar"></i> Repair Estimates</a>
                <a href="vehicleowner-repair-tracking.php" class="nav-item"><i class="fa-solid fa-wrench"></i> Live Repair Tracking</a>
                <a href="vehicleowner-service-history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
                <a href="vehicleowner-invoices.php" class="nav-item"><i class="fa-solid fa-receipt"></i> Invoices</a>
                <a href="vehicleowner-chat.php" class="nav-item"><i class="fa-solid fa-comment"></i> Chat</a>
                <a href="vehicleowner-notifications.php" class="nav-item"><i class="fa-solid fa-bell"></i> Notifications</a>
            </nav>

            <a class="logout" href="../logout.php">
                <span><i class="fa-solid fa-right-from-bracket"></i></span>Logout
            </a>

        </aside>


        <!-- MAIN -->
        <main class="main">

            <!-- Top Bar -->
            <header class="topbar">
                <div class="search">
                    <div class="search-icon">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </div>
                    <input type="text" placeholder="Search managers, workshops...">
                </div>

                <div class="top-actions">
                    <div class="notification">
                        <svg viewBox="0 0 24 24">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <b></b>
                    </div>

                    <div class="mail">
                        <svg viewBox="0 0 24 24">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z">
                            </path>
                            <polyline points="22,6 12,13 2,6"></polyline>
                        </svg>
                    </div>

                    <div class="avatar" title="<?= e($user['name']) ?>"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
                </div>
            </header>

            <!-- Page content -->
            <section class="content">
                <div class="vo-wrap">

                    <!-- Left: booking form -->
                    <div class="vo-form-col">

                        <div class="vo-header">
                            <h1>Book Service Appointment</h1>
                            <p>Schedule maintenance or repairs for your vehicles in just a few steps.</p>
                        </div>

                        <form class="vo-form" id="vo-booking-form" novalidate>

                            <!-- Step 1: Vehicle + Workshop -->
                            <div class="vo-step-row">
                                <div class="vo-card">
                                    <label class="vo-field-label" for="vo-vehicle"><i class="fa-solid fa-car"></i> 1. Select Vehicle</label>
                                    <div class="vo-select-wrap">
                                        <select id="vo-vehicle" name="vehicle_id" required>
                                            <?php if (empty($vehicles)): ?>
                                            <option value="">No vehicles registered</option>
                                            <?php endif; ?>
                                            <?php foreach ($vehicles as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>"<?= $v['id'] === $selectedVehicle ? ' selected' : '' ?>><?= e($v['year'] . ' ' . $v['make'] . ' ' . $v['model'] . ' (' . $v['license_plate'] . ')') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fa-solid fa-chevron-down vo-select-chevron"></i>
                                    </div>
                                </div>
                                <div class="vo-card">
                                    <label class="vo-field-label" for="vo-workshop"><i class="fa-solid fa-shop"></i> 2. Select Workshop</label>
                                    <div class="vo-select-wrap">
                                        <select id="vo-workshop" name="workshop_id" required>
                                            <?php foreach ($workshops as $w): ?>
                                            <option value="<?= (int) $w['id'] ?>"><?= e($w['name'] . ' — ' . $w['location']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fa-solid fa-chevron-down vo-select-chevron"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: Service details -->
                            <div class="vo-card vo-card--split">
                                <div class="vo-card__top">
                                    <p class="vo-field-label"><i class="fa-solid fa-clock"></i> 3. Service Details</p>
                                    <div class="vo-categories" style="flex-wrap: wrap;">
                                        <?php foreach ($categories as $i => $c): ?>
                                        <label class="vo-category" style="min-width: 130px;" title="<?= e($c['description']) ?>">
                                            <input type="radio" name="service_category_id" value="<?= (int) $c['id'] ?>"<?= $i === 0 ? ' checked' : '' ?>>
                                            <span class="vo-category__icon"><i class="fa-solid <?= category_icon($c['name']) ?>"></i></span>
                                            <span class="vo-category__label"><?= e($c['name']) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="vo-card__bottom">
                                    <div class="vo-field">
                                        <label class="vo-field__label" for="vo-date">Preferred Date</label>
                                        <input class="vo-input" type="date" id="vo-date" name="preferred_date" required min="<?= e(date('Y-m-d', now_ts())) ?>" max="<?= e(date('Y-m-d', strtotime('+6 months', now_ts()))) ?>">
                                    </div>
                                    <div class="vo-field">
                                        <label class="vo-field__label" for="vo-time">Preferred Time</label>
                                        <div class="vo-select-wrap">
                                            <select id="vo-time" name="time_slot">
                                                <?php foreach (TIME_SLOTS as $value => $slot): ?>
                                                <option value="<?= e($value) ?>"><?= e($slot[0]) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fa-solid fa-chevron-down vo-select-chevron"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Additional details -->
                            <div class="vo-card">
                                <label class="vo-field-label" for="vo-description"><i class="fa-solid fa-file-lines"></i> 4. Additional Details</label>
                                <textarea class="vo-textarea" id="vo-description" name="description" rows="3" maxlength="2000" placeholder="Describe the issue or any specific requests (e.g., 'Brakes are squeaking', 'Needs 60k mile service')..."></textarea>
                                <label class="vo-upload" for="vo-photos">
                                    <i class="fa-solid fa-camera"></i>
                                    <span class="vo-upload__title">Click to upload photos or drag and drop</span>
                                    <span class="vo-upload__hint">PNG, JPG up to 10MB (Optional)</span>
                                    <input type="file" id="vo-photos" name="photos" accept="image/png, image/jpeg" multiple hidden>
                                </label>
                            </div>

                            <!-- Actions -->
                            <div class="vo-actions">
                                <button type="button" class="vo-btn vo-btn--soft" id="vo-save-draft">Save Draft</button>
                                <button type="submit" class="vo-btn vo-btn--primary"><i class="fa-solid fa-calendar-check"></i> Book Appointment</button>
                            </div>
                        </form>
                    </div>

                    <!-- Right: upcoming + promo -->
                    <aside class="vo-aside">
                        <section class="vo-upcoming">
                            <div class="vo-upcoming__head">
                                <h3>Upcoming</h3>
                                <i class="fa-regular fa-calendar"></i>
                            </div>
                            <ul class="vo-upcoming__list">
                                <?php if (empty($upcoming)): ?>
                                <li class="vo-upcoming__item vo-upcoming__item--last">
                                    <p class="vo-upcoming__location">No upcoming appointments.</p>
                                </li>
                                <?php endif; ?>
                                <?php foreach ($upcoming as $i => $appt):
                                    $last = $i === count($upcoming) - 1 ? ' vo-upcoming__item--last' : '';
                                    $confirmed = $appt['status'] === 'Approved';
                                ?>
                                <li class="vo-upcoming__item<?= $last ?>">
                                    <div class="vo-upcoming__row">
                                        <span class="vo-tag <?= $confirmed ? 'vo-tag--confirmed' : 'vo-tag--pending' ?>"><?= $confirmed ? 'Confirmed' : 'Pending' ?></span>
                                        <span class="vo-upcoming__time"><?= e(date('M d, g:i A', strtotime($appt['preferred_date']))) ?></span>
                                    </div>
                                    <h4><?= e($appt['make'] . ' ' . $appt['model'] . ' - ' . ($appt['category_name'] ?: 'Service')) ?></h4>
                                    <p class="vo-upcoming__location"><i class="fa-solid fa-location-dot"></i> <?= e($appt['workshop_name'] ?: 'Workshop') ?></p>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <a href="vehicleowner-service-history.php?view=upcoming" class="vo-upcoming__footer">View All Appointments</a>
                        </section>

                        <section class="vo-promo">
                            <img src="../../assets/images/premium-care-plan-promo.jpg" alt="Mechanic servicing a vehicle in the AutoCare workshop">
                            <div class="vo-promo__overlay">
                                <h4>Premium Care Plan</h4>
                                <p>Upgrade today and save 15% on parts.</p>
                            </div>
                        </section>
                    </aside>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-book-appointment.js"></script>
</body>

</html>
