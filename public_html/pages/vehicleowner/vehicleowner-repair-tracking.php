<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$activeJobs = owner_jobs($pdo, $ownerId, true);
$requestedId = input_id($_GET['job_id'] ?? null);
$jobId = $requestedId ?: ($activeJobs[0]['id'] ?? null);
$job = $jobId ? owner_job_detail($pdo, $ownerId, $jobId) : null;

// The four customer-facing stages shown on this page (REPAIR_STEPS minus "Received")
$stages = [
    1 => ['Diagnosis', 'fa-magnifying-glass'],
    2 => ['Repairing', 'fa-wrench'],
    3 => ['Testing', 'fa-vial'],
    4 => ['Ready for Pickup', 'fa-key'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Live Repair Tracking</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-repair-tracking.css">
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
                <a href="vehicleowner-book-appointment.php" class="nav-item"><i class="fa-solid fa-calendar-check"></i> Service Appointments</a>
                <a href="vehicleowner-repair-estimates.php" class="nav-item"><i class="fa-solid fa-file-invoice-dollar"></i> Repair Estimates</a>
                <a href="vehicleowner-repair-tracking.php" class="nav-item active"><i class="fa-solid fa-wrench"></i> Live Repair Tracking</a>
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
                            <h1>Live Repair Tracking</h1>
                            <p>Real-time updates for your vehicle service.</p>
                        </div>
                        <div class="vo-header__actions">
                            <?php if (count($activeJobs) > 1): ?>
                            <label class="visually-hidden" for="vo-job-switch" style="position: absolute; left: -9999px;">Choose repair</label>
                            <select id="vo-job-switch" style="padding: 9px 12px; border-radius: 8px; border: 1px solid #c5c5d4; font: inherit;">
                                <?php foreach ($activeJobs as $aj): ?>
                                <option value="<?= (int) $aj['id'] ?>"<?= $job && $aj['id'] === $job['id'] ? ' selected' : '' ?>><?= e($aj['code'] . ' · ' . $aj['make'] . ' ' . $aj['model']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <a href="vehicleowner-repair-estimates.php" class="vo-btn vo-btn--soft" style="text-decoration: none;"><i class="fa-solid fa-file-lines"></i> View Estimate</a>
                            <a href="vehicleowner-chat.php<?= $job && $job['manager_id'] ? '?contact_id=' . (int) $job['manager_id'] : '' ?>" class="vo-btn vo-btn--primary" style="text-decoration: none;"><i class="fa-solid fa-paper-plane"></i> Message Manager</a>
                        </div>
                    </div>

                    <?php if (!$job): ?>
                    <section class="vo-card">
                        <h3><?= $requestedId ? 'Repair not found' : 'No active repairs' ?></h3>
                        <p class="vo-card__desc">
                            <?= $requestedId ? 'This repair does not exist or is not linked to your account.' : 'None of your vehicles are in the workshop right now.' ?>
                            <a href="vehicleowner-book-appointment.php">Book a service</a> or check your <a href="vehicleowner-service-history.php">service history</a>.
                        </p>
                    </section>
                    <?php else:
                        $activeStage = max(1, $job['step_index']);
                        $isDone = in_array($job['status'], ['Completed', 'Delivered', 'Ready'], true);
                    ?>
                    <!-- Bento grid -->
                    <div class="vo-grid" data-job-id="<?= (int) $job['id'] ?>">

                        <!-- Left: vehicle status card -->
                        <section class="vo-vehicle-card">
                            <div class="vo-vehicle-card__head">
                                <div class="vo-vehicle-card__id">
                                    <span class="vo-vehicle-icon"><i class="fa-solid fa-car"></i></span>
                                    <div>
                                        <h2><?= e($job['make'] . ' ' . $job['model']) ?></h2>
                                        <div class="vo-vehicle-card__meta">
                                            <span class="vo-plate"><?= e($job['license_plate']) ?></span>
                                            <span class="vo-vehicle-card__desc"><?= (int) $job['year'] ?></span>
                                        </div>
                                    </div>
                                </div>
                                <span class="vo-status-pill"><span class="vo-status-pill__dot"></span> <?= e($job['status']) ?><?= $isDone ? '' : ' - Active' ?></span>
                            </div>

                            <div class="vo-timeline">
                                <h3>Repair Progress</h3>
                                <div class="vo-stages">
                                    <div class="vo-stages__line"></div>
                                    <div class="vo-stages__line vo-stages__line--active" style="width: calc(80% * <?= $activeStage - 1 ?> / 3);"></div>

                                    <?php foreach ($stages as $idx => [$label, $iconClass]):
                                        $state = ($idx < $activeStage || ($isDone && $idx === 4)) ? 'done' : ($idx === $activeStage ? 'active' : 'pending');
                                        $time = $job['step_times'][$idx] ?? null;
                                    ?>
                                    <div class="vo-stage<?= $state !== 'pending' ? ' vo-stage--' . $state : '' ?>">
                                        <span class="vo-stage__icon">
                                            <i class="fa-solid <?= $state === 'done' ? 'fa-check' : $iconClass ?>"></i>
                                            <?php if ($state === 'active'): ?><span class="vo-stage__ping"></span><?php endif; ?>
                                        </span>
                                        <div class="vo-stage__label">
                                            <p class="vo-stage__name<?= $state === 'active' ? ' vo-stage__name--active' : '' ?>"><?= e($label) ?></p>
                                            <?php if ($state === 'done'): ?>
                                            <p class="vo-stage__status vo-stage__status--done">Completed</p>
                                            <?php elseif ($state === 'active'): ?>
                                            <p class="vo-stage__status vo-stage__status--active"><?= e($job['status'] === 'Awaiting Parts' ? 'Awaiting Parts' : 'Active Now') ?></p>
                                            <?php else: ?>
                                            <p class="vo-stage__status">Pending</p>
                                            <?php endif; ?>
                                            <?php if ($time): ?>
                                            <p class="vo-stage__time"><?= e(date('M d, g:i A', strtotime($time))) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="vo-mechanic-banner">
                                <div class="vo-mechanic-banner__who">
                                    <?php $avatar = safe_avatar_url($job['mechanic_avatar']); ?>
                                    <?php if ($job['mechanic_name'] && $avatar): ?>
                                    <img src="<?= e($avatar) ?>" alt="<?= e($job['mechanic_name']) ?>, assigned mechanic" class="vo-mechanic-avatar">
                                    <?php else: ?>
                                    <span class="vo-mechanic-avatar" style="display: inline-flex; align-items: center; justify-content: center; background: #e9e7f0; font-weight: 700;"><?= e($job['mechanic_name'] ? initials($job['mechanic_name']) : '?') ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <p class="vo-mechanic-banner__label">Assigned Mechanic</p>
                                        <p class="vo-mechanic-banner__name"><?= e($job['mechanic_name'] ? $job['mechanic_name'] . ($job['mechanic_specialty'] ? ' (' . $job['mechanic_specialty'] . ')' : '') : 'Not assigned yet') ?></p>
                                    </div>
                                </div>
                                <div class="vo-mechanic-banner__eta">
                                    <i class="fa-solid fa-clock"></i>
                                    <div>
                                        <p class="vo-mechanic-banner__label">Est. Completion</p>
                                        <p class="vo-mechanic-banner__name"><?= e($job['delivery_date'] ?: 'To be confirmed') ?></p>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Right: gallery + service details -->
                        <aside class="vo-col-right">
                            <section class="vo-card">
                                <div class="vo-card__head">
                                    <h3>Live Gallery</h3>
                                    <span class="vo-badge"><?= count($job['photos']) ?> Photo<?= count($job['photos']) === 1 ? '' : 's' ?></span>
                                </div>
                                <p class="vo-card__desc">Photos uploaded directly from the service bay.</p>
                                <?php if (empty($job['photos'])): ?>
                                <p class="vo-card__desc">No photos have been uploaded for this repair yet.</p>
                                <?php else: ?>
                                <div class="vo-gallery">
                                    <?php foreach (array_slice($job['photos'], 0, 3) as $photo):
                                        $src = $photo['photo_url'];
                                    ?>
                                    <figure class="vo-gallery__item" data-photo-src="<?= e($src) ?>" data-photo-caption="<?= e($photo['description'] ?? '') ?>" style="cursor: zoom-in;">
                                        <img src="<?= e($src) ?>" alt="<?= e($photo['description'] ?: 'Repair photo') ?>">
                                        <span class="vo-gallery__overlay"><i class="fa-solid fa-expand"></i></span>
                                        <span class="vo-gallery__time"><?= e(date('g:i A', strtotime($photo['uploaded_at']))) ?></span>
                                    </figure>
                                    <?php endforeach; ?>
                                    <button type="button" class="vo-gallery__more" id="vo-gallery-all">
                                        <i class="fa-solid fa-images"></i>
                                        <span>View All (<?= count($job['photos']) ?>)</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                            </section>

                            <section class="vo-card">
                                <h3>Service Details</h3>
                                <dl class="vo-details">
                                    <div class="vo-details__row">
                                        <dt>Service Type</dt>
                                        <dd><?= e($job['category_name'] ?: $job['service_text']) ?></dd>
                                    </div>
                                    <div class="vo-details__row">
                                        <dt>RO Number</dt>
                                        <dd><?= e($job['work_order'] ?: $job['code']) ?></dd>
                                    </div>
                                    <div class="vo-details__row vo-details__row--last">
                                        <?php if ($job['approved_total'] !== null): ?>
                                        <dt>Approved Total</dt>
                                        <dd><?= e(money($job['approved_total'])) ?></dd>
                                        <?php else: ?>
                                        <dt>Estimated Total</dt>
                                        <dd><?= e(money($job['estimated_cost'])) ?></dd>
                                        <?php endif; ?>
                                    </div>
                                </dl>
                            </section>
                        </aside>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-repair-tracking.js"></script>
</body>

</html>
