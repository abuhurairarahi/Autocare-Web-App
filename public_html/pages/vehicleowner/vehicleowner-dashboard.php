<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$dash = owner_dashboard($pdo, $ownerId);
$job = $dash['active_job'];
$firstName = explode(' ', trim($user['name']))[0];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Dashboard</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-dashboard.css">
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
                <a href="vehicleowner-dashboard.php" class="nav-item active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="vehicleowner-vehicles.php" class="nav-item"><i class="fa-solid fa-car"></i> My Vehicles</a>
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
                            <h1>Welcome back, <?= e($firstName) ?></h1>
                            <p>Here is the latest status of your vehicles.</p>
                        </div>
                        <a href="vehicleowner-book-appointment.php" class="vo-btn vo-btn--primary" style="text-decoration: none;"><i class="fa-solid fa-plus"></i> New Appointment</a>
                    </div>

                    <!-- KPI cards -->
                    <div class="vo-kpis">
                        <div class="vo-kpi">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--vehicles"><i class="fa-solid fa-car"></i></span>
                                <span class="vo-kpi__value"><?= $dash['vehicle_count'] ?></span>
                            </div>
                            <p class="vo-kpi__label">Registered Vehicles</p>
                        </div>
                        <div class="vo-kpi">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--appointments"><i class="fa-solid fa-calendar-check"></i></span>
                                <span class="vo-kpi__value"><?= $dash['upcoming_count'] ?></span>
                            </div>
                            <p class="vo-kpi__label">Upcoming Appointments</p>
                        </div>
                        <div class="vo-kpi vo-kpi--repairs">
                            <span class="vo-kpi__glow" aria-hidden="true"></span>
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--repairs"><i class="fa-solid fa-wrench"></i></span>
                                <span class="vo-kpi__value"><?= $dash['active_repairs'] ?></span>
                            </div>
                            <p class="vo-kpi__label">Active Repairs</p>
                        </div>
                        <div class="vo-kpi vo-kpi--approvals">
                            <div class="vo-kpi__top">
                                <span class="vo-kpi__icon vo-kpi__icon--approvals"><i class="fa-solid fa-triangle-exclamation"></i></span>
                                <span class="vo-kpi__value"><?= $dash['pending_approvals'] ?></span>
                            </div>
                            <p class="vo-kpi__label">Pending Approvals</p>
                        </div>
                    </div>

                    <!-- Main layout -->
                    <div class="vo-main-grid">

                        <!-- Left column -->
                        <div class="vo-col-left">

                            <!-- Active repair tracker -->
                            <section class="vo-panel">
                                <div class="vo-panel__head">
                                    <h3><i class="fa-solid fa-wave-square"></i> Live Repair Tracking</h3>
                                    <?php if ($job): ?>
                                    <span class="vo-pill"><?= e($job['status']) ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$job): ?>
                                <p style="color: var(--vo-text-muted); margin: 8px 0 0;">No vehicles are in the workshop right now.</p>
                                <?php else: ?>
                                <div class="vo-repair-summary">
                                    <!-- No vehicle photo column: neutral placeholder -->
                                    <span class="vo-repair-summary__img" style="display: flex; align-items: center; justify-content: center; font-size: 32px; color: var(--vo-text-muted);"><i class="fa-solid fa-car-side" aria-hidden="true"></i></span>
                                    <div class="vo-repair-summary__info">
                                        <h4><?= e($job['make'] . ' ' . $job['model']) ?> <span>- <?= (int) $job['year'] ?></span></h4>
                                        <p class="vo-repair-summary__service"><?= e($job['service_text']) ?></p>
                                        <p class="vo-repair-summary__eta">Est. Completion: <?= e($job['delivery_date'] ?: 'To be confirmed') ?></p>
                                    </div>
                                </div>

                                <div class="vo-progress">
                                    <div class="vo-progress__stages">
                                        <?php foreach (REPAIR_STEPS as $i => $step):
                                            $state = $i < $job['step_index'] ? 'done' : ($i === $job['step_index'] ? 'active' : 'pending');
                                        ?>
                                        <div class="vo-progress__stage vo-progress__stage--<?= $state ?>">
                                            <?php if ($state === 'done'): ?>
                                            <span class="vo-progress__dot"><i class="fa-solid fa-check"></i></span>
                                            <?php elseif ($state === 'active'): ?>
                                            <span class="vo-progress__dot vo-progress__dot--active"><i class="fa-solid fa-wrench"></i></span>
                                            <?php else: ?>
                                            <span class="vo-progress__dot vo-progress__dot--empty"></span>
                                            <?php endif; ?>
                                            <p><?= e($step) ?></p>
                                        </div>
                                        <?php endforeach; ?>
                                        <div class="vo-progress__track">
                                            <div class="vo-progress__fill" style="width: <?= (int) round($job['step_index'] / (count(REPAIR_STEPS) - 1) * 100) ?>%;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="vo-panel__actions">
                                    <a href="vehicleowner-repair-estimates.php" class="vo-btn vo-btn--soft" style="text-decoration: none;">View Estimate</a>
                                    <?php if ($job['mechanic_id']): ?>
                                    <a href="vehicleowner-chat.php?contact_id=<?= (int) $job['mechanic_id'] ?>" class="vo-btn vo-btn--soft" style="text-decoration: none;">Message Tech</a>
                                    <?php else: ?>
                                    <a href="vehicleowner-chat.php" class="vo-btn vo-btn--soft" style="text-decoration: none;">Message Workshop</a>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </section>

                            <!-- Latest invoices -->
                            <section class="vo-panel">
                                <div class="vo-panel__head">
                                    <h3><i class="fa-solid fa-file-invoice"></i> Latest Invoices</h3>
                                    <a href="vehicleowner-invoices.php" class="vo-link">View All</a>
                                </div>
                                <div class="vo-table-wrap">
                                    <table class="vo-table">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
                                                <th>Date</th>
                                                <th>Vehicle</th>
                                                <th class="vo-table__num">Amount</th>
                                                <th class="vo-table__center">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($dash['latest_invoices'])): ?>
                                            <tr><td colspan="5" class="vo-table__center" style="color: var(--vo-text-muted);">No invoices yet.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($dash['latest_invoices'] as $inv): ?>
                                            <tr>
                                                <td class="vo-table__id"><?= e($inv['invoice_number']) ?></td>
                                                <td><?= e(date('M d, Y', strtotime($inv['issued_date']))) ?></td>
                                                <td><?= e($inv['make'] ? $inv['make'] . ' ' . $inv['model'] : '—') ?></td>
                                                <td class="vo-table__num"><?= e(money($inv['total_amount'])) ?></td>
                                                <td class="vo-table__center">
                                                    <?php if ($inv['status'] === 'Paid'): ?>
                                                    <span class="vo-status vo-status--paid">Paid</span>
                                                    <?php else: ?>
                                                    <span class="vo-status" style="background: #fef3c7; color: #92400e;"><?= e($inv['status']) ?></span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <!-- Right column -->
                        <div class="vo-col-right">

                            <!-- Action required -->
                            <section class="vo-panel">
                                <h3 class="vo-panel__title"><i class="fa-solid fa-bullhorn"></i> Action Required</h3>
                                <div class="vo-alerts">
                                    <?php foreach ($dash['awaiting_estimates'] as $est): ?>
                                    <div class="vo-alert vo-alert--danger">
                                        <div class="vo-alert__head">
                                            <span class="vo-alert__title">Estimate Approval Needed</span>
                                            <span class="vo-alert__time"><?= e($est['sent_date'] !== '-' ? $est['sent_date'] : date('M d', strtotime($est['created_at']))) ?></span>
                                        </div>
                                        <p class="vo-alert__desc"><?= e($est['code'] . ': ' . $est['service_text'] . ' for ' . $est['make'] . ' ' . $est['model'] . ' (' . money($est['total_estimated_cost']) . ')') ?></p>
                                        <a href="vehicleowner-repair-estimates.php" class="vo-alert__action vo-alert__action--danger">Review Estimate</a>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php foreach ($dash['unpaid_invoices'] as $inv): ?>
                                    <div class="vo-alert">
                                        <div class="vo-alert__head">
                                            <span class="vo-alert__title">Invoice <?= e($inv['status']) ?></span>
                                            <span class="vo-alert__time"><?= e(date('M d', strtotime($inv['issued_date']))) ?></span>
                                        </div>
                                        <p class="vo-alert__desc"><?= e($inv['invoice_number'] . ' for ' . money($inv['total_amount']) . ' is awaiting payment.') ?></p>
                                        <a href="vehicleowner-invoices.php" class="vo-alert__action">View Invoice</a>
                                    </div>
                                    <?php endforeach; ?>
                                    <!-- Static: there is no maintenance schedule / mileage column to derive "maintenance due" from -->
                                    <div class="vo-alert">
                                        <div class="vo-alert__head">
                                            <span class="vo-alert__title">Routine Maintenance Due</span>
                                            <span class="vo-alert__time">1d ago</span>
                                        </div>
                                        <p class="vo-alert__desc">Ford F-150 is due for an oil change next week.</p>
                                        <a href="vehicleowner-book-appointment.php" class="vo-alert__action">Schedule Now</a>
                                    </div>
                                </div>
                            </section>

                            <!-- Upcoming schedule -->
                            <section class="vo-panel vo-panel--schedule">
                                <h3 class="vo-panel__title"><i class="fa-solid fa-calendar-days"></i> Upcoming Schedule</h3>
                                <ul class="vo-schedule">
                                    <?php if (empty($dash['upcoming'])): ?>
                                    <li class="vo-schedule__item vo-schedule__item--muted">
                                        <p class="vo-schedule__desc">No upcoming appointments. <a href="vehicleowner-book-appointment.php">Book one</a>.</p>
                                    </li>
                                    <?php endif; ?>
                                    <?php foreach ($dash['upcoming'] as $i => $appt):
                                        $muted = $i > 0 ? ' vo-schedule__item--muted' : '';
                                    ?>
                                    <li class="vo-schedule__item<?= $muted ?>">
                                        <span class="vo-schedule__dot<?= $i > 0 ? ' vo-schedule__dot--muted' : '' ?>"></span>
                                        <p class="vo-schedule__time<?= $i > 0 ? ' vo-schedule__time--muted' : '' ?>"><?= e(date('M d', strtotime($appt['preferred_date']))) ?> &bull; <?= e(date('g:i A', strtotime($appt['preferred_date']))) ?></p>
                                        <p class="vo-schedule__vehicle"><?= e($appt['make'] . ' ' . $appt['model']) ?></p>
                                        <p class="vo-schedule__desc"><?= e(($appt['category_name'] ?: 'Service') . ' (' . $appt['status'] . ')') ?></p>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </section>
                        </div>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
</body>

</html>
