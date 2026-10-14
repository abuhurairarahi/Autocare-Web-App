<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$vehicles = owner_vehicles($pdo, $ownerId);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - My Vehicles</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-vehicles.css">
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
                <a href="vehicleowner-vehicles.php" class="nav-item active"><i class="fa-solid fa-car"></i> My Vehicles</a>
                <a href="vehicleowner-book-appointment.php" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Service Appointments</a>
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

                    <!-- Page header -->
                    <div class="vo-header">
                        <div class="vo-header__text">
                            <h1>Vehicle Management</h1>
                            <p>View and manage your registered fleet.</p>
                        </div>
                        <button type="button" id="vo-add-vehicle" class="vo-vcard__btn" style="border: none; cursor: pointer; gap: 8px; background: var(--vo-navy); color: #ffffff;"><i class="fa-solid fa-plus"></i> Add Vehicle</button>
                    </div>

                    <!-- Vehicle cards -->
                    <div class="vo-vehicles-grid">
                        <?php if (empty($vehicles)): ?>
                        <p style="color: var(--vo-text-muted);">You have not registered any vehicles yet. Use <strong>Add Vehicle</strong> to get started.</p>
                        <?php endif; ?>

                        <?php foreach ($vehicles as $v):
                            $inShop = $v['active_job_status'] !== null;
                            $plate = $v['license_plate'];
                            $dash = strpos($plate, '-');
                        ?>
                        <article class="vo-vcard" data-vehicle-id="<?= (int) $v['id'] ?>">
                            <!-- No vehicle photo column: show a neutral placeholder -->
                            <div class="vo-vcard__media" style="display: flex; align-items: center; justify-content: center; color: var(--vo-text-muted); font-size: 56px;">
                                <i class="fa-solid fa-car-side" aria-hidden="true"></i>
                                <?php if ($inShop): ?>
                                <span class="vo-vcard__status vo-vcard__status--shop"><span class="vo-vcard__status-dot"></span> In Shop</span>
                                <?php else: ?>
                                <span class="vo-vcard__status vo-vcard__status--active"><span class="vo-vcard__status-dot"></span> Active</span>
                                <?php endif; ?>
                            </div>
                            <div class="vo-vcard__body">
                                <div class="vo-vcard__top">
                                    <div class="vo-vcard__id">
                                        <h3><?= e($v['make'] . ' ' . $v['model']) ?></h3>
                                        <p><?= (int) $v['year'] ?></p>
                                    </div>
                                    <?php if ($dash !== false && strlen($plate) <= 12): ?>
                                    <span class="vo-plate"><?= e(substr($plate, 0, $dash + 1)) ?><br><?= e(substr($plate, $dash + 1)) ?></span>
                                    <?php else: ?>
                                    <span class="vo-plate vo-plate--single"><?= e($plate) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="vo-vcard__specs">
                                    <div class="vo-vcard__spec">
                                        <p class="vo-vcard__spec-label">VIN</p>
                                        <p class="vo-vcard__spec-value vo-vcard__spec-value--vin"><?= e($v['vin'] ?: 'Not provided') ?></p>
                                    </div>
                                    <!-- Odometer and fuel type have no column in Vehicles -->
                                    <div class="vo-vcard__spec">
                                        <p class="vo-vcard__spec-label">Odometer</p>
                                        <p class="vo-vcard__spec-value">&mdash;</p>
                                    </div>
                                    <div class="vo-vcard__spec">
                                        <p class="vo-vcard__spec-label">Fuel Type</p>
                                        <p class="vo-vcard__spec-value">&mdash;</p>
                                    </div>
                                    <?php if ($inShop): ?>
                                    <div class="vo-vcard__spec">
                                        <p class="vo-vcard__spec-label">Status</p>
                                        <p class="vo-vcard__spec-value vo-vcard__spec-value--accent"><i class="fa-solid fa-wrench"></i> <?= e($v['active_job_status']) ?></p>
                                    </div>
                                    <?php else: ?>
                                    <div class="vo-vcard__spec">
                                        <p class="vo-vcard__spec-label">Next Service</p>
                                        <?php if ($v['next_appointment']): ?>
                                        <p class="vo-vcard__spec-value vo-vcard__spec-value--muted"><i class="fa-regular fa-calendar-check"></i> <?= e(date('M d, Y', strtotime($v['next_appointment']))) ?></p>
                                        <?php else: ?>
                                        <p class="vo-vcard__spec-value vo-vcard__spec-value--muted">Not booked</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="vo-vcard__actions">
                                    <a href="vehicleowner-service-history.php" class="vo-vcard__link">History</a>
                                    <?php if ($inShop): ?>
                                    <a href="vehicleowner-repair-tracking.php" class="vo-vcard__btn">Track Repair</a>
                                    <?php else: ?>
                                    <a href="vehicleowner-book-appointment.php?vehicle_id=<?= (int) $v['id'] ?>" class="vo-vcard__btn">Book Service</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-vehicles.js"></script>
</body>

</html>
