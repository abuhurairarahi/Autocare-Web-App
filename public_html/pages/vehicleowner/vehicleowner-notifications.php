<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$notifications = owner_notifications($pdo, $ownerId);

// Group into Today / Yesterday / Earlier
$groups = ['Today' => [], 'Yesterday' => [], 'Earlier' => []];
$today = date('Y-m-d', now_ts());
$yesterday = date('Y-m-d', now_ts() - 86400);
foreach ($notifications as $n) {
    $day = substr($n['time'], 0, 10);
    $groups[$day === $today ? 'Today' : ($day === $yesterday ? 'Yesterday' : 'Earlier')][] = $n;
}
$unreadCount = count(array_filter($notifications, fn ($n) => $n['unread']));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Notifications</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/vehicleowner-sidebar-topbar-style.css">
    <style>.vo-notification[hidden], .vo-notif-group[hidden] { display: none; }</style>
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
                <a href="vehicleowner-repair-tracking.php" class="nav-item"><i class="fa-solid fa-wrench"></i> Live Repair Tracking</a>
                <a href="vehicleowner-service-history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
                <a href="vehicleowner-invoices.php" class="nav-item"><i class="fa-solid fa-receipt"></i> Invoices</a>
                <a href="vehicleowner-chat.php" class="nav-item"><i class="fa-solid fa-comment"></i> Chat</a>
                <a href="vehicleowner-notifications.php" class="nav-item active"><i class="fa-solid fa-bell"></i> Notifications</a>
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
                            <h1>Notifications</h1>
                            <p>Manage your workshop updates and alerts.</p>
                        </div>
                        <div class="vo-header__actions">
                            <button type="button" class="vo-btn-outline" id="vo-unread-only" aria-pressed="false"><i class="fa-solid fa-filter"></i> Unread only</button>
                            <button type="button" class="vo-btn vo-btn--mark-read" id="vo-mark-all"<?= $unreadCount ? '' : ' disabled' ?>><i class="fa-solid fa-check-double"></i> Mark all as read</button>
                        </div>
                    </div>

                    <!-- Filter chips -->
                    <div class="vo-chips" role="group" aria-label="Filter notifications">
                        <button type="button" class="vo-chip vo-chip--active" data-filter="all">All Updates</button>
                        <button type="button" class="vo-chip" data-filter="appointments">Appointments</button>
                        <button type="button" class="vo-chip" data-filter="billing">Billing</button>
                        <button type="button" class="vo-chip" data-filter="repairs">Repairs</button>
                        <button type="button" class="vo-chip" data-filter="messages">Messages</button>
                        <button type="button" class="vo-chip" data-filter="offers">Offers</button>
                    </div>

                    <!-- Notification groups -->
                    <div class="vo-notif-groups">
                        <?php if (empty($notifications)): ?>
                        <p style="color: #757684;">You have no notifications yet.</p>
                        <?php endif; ?>

                        <?php foreach ($groups as $label => $items): if (!$items) continue;
                            $newCount = count(array_filter($items, fn ($n) => $n['unread']));
                        ?>
                        <section class="vo-notif-group">
                            <div class="vo-notif-group__head">
                                <h2><?= e($label) ?></h2>
                                <?php if ($newCount): ?>
                                <span class="vo-count-badge"><?= $newCount ?> New</span>
                                <?php endif; ?>
                            </div>
                            <div class="vo-notif-list">
                                <?php foreach ($items as $n):
                                    $ts = strtotime($n['time']);
                                    $time = $label === 'Earlier' ? date('D, M d', $ts) : date('g:i A', $ts);
                                ?>
                                <article class="vo-notification<?= $n['unread'] ? ' vo-notification--unread' : '' ?>" data-category="<?= e($n['category']) ?>" data-unread="<?= $n['unread'] ? '1' : '0' ?>">
                                    <span class="vo-notification__icon vo-notification__icon--<?= e($n['color']) ?>"><i class="fa-solid <?= e($n['icon']) ?>"></i></span>
                                    <div class="vo-notification__body">
                                        <div class="vo-notification__row">
                                            <h3><?= e($n['title']) ?></h3>
                                            <span class="vo-notification__time"><?= e($time) ?></span>
                                        </div>
                                        <p class="vo-notification__desc"><?= e($n['body']) ?></p>
                                        <a href="<?= e($n['link']) ?>" class="<?= $n['unread'] ? 'vo-btn vo-btn--primary-sm' : 'vo-btn-outline vo-btn-outline--sm' ?>" style="text-decoration: none;"><?= $n['category'] === 'messages' ? 'Reply' : 'View Details' ?></a>
                                    </div>
                                    <?php if ($n['unread']): ?>
                                    <span class="vo-unread-dot" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                        <?php endforeach; ?>

                        <p id="vo-notif-empty" hidden style="color: #757684;">Nothing here for this filter.</p>
                        <div class="vo-load-more">
                            <button type="button" class="vo-btn-pill" id="vo-load-more" hidden>Load More</button>
                        </div>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-notifications.js"></script>
</body>

</html>
