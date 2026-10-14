<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/chat-common.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$contacts = chat_contacts($pdo, $ownerId, 'VehicleOwner');
$active = null;
$wanted = input_id($_GET['contact_id'] ?? null);
foreach ($contacts as $c) {
    if ($c['id'] === $wanted) {
        $active = $c;
    }
}
$active = $active ?? ($contacts[0] ?? null);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Chat</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-chat.css">
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
                <a href="vehicleowner-repair-tracking.php" class="nav-item"><i class="fa-solid fa-wrench"></i> Live Repair Tracking</a>
                <a href="vehicleowner-service-history.php" class="nav-item"><i class="fa-solid fa-clock-rotate-left"></i> Service History</a>
                <a href="vehicleowner-invoices.php" class="nav-item"><i class="fa-solid fa-receipt"></i> Invoices</a>
                <a href="vehicleowner-chat.php" class="nav-item active"><i class="fa-solid fa-comment"></i> Chat</a>
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
                <div class="vo-chat" data-contact-id="<?= $active ? (int) $active['id'] : '' ?>" data-contact-name="<?= e($active['name'] ?? '') ?>" data-contact-avatar="<?= e($active['avatar'] ?? '') ?>">

                    <!-- Conversation list -->
                    <aside class="vo-chat__sidebar">
                        <div class="vo-chat__sidebar-head">
                            <h2>Messages</h2>
                            <div class="vo-search">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="vo-chat-search" placeholder="Search conversations..." aria-label="Search conversations">
                            </div>
                        </div>
                        <ul class="vo-conversations">
                            <?php if (empty($contacts)): ?>
                            <li style="padding: 16px; color: #757684;">No conversations yet.</li>
                            <?php endif; ?>
                            <?php foreach ($contacts as $c):
                                $classes = 'vo-conversation';
                                if ($active && $c['id'] === $active['id']) $classes .= ' vo-conversation--active';
                                elseif ($c['unread'] > 0) $classes .= ' vo-conversation--unread';
                            ?>
                            <li>
                                <button type="button" class="<?= $classes ?>" data-contact-id="<?= (int) $c['id'] ?>" data-contact-name="<?= e($c['name']) ?>">
                                    <span class="vo-avatar vo-avatar--md">
                                        <?php if ($c['avatar']): ?>
                                        <img src="<?= e($c['avatar']) ?>" alt="<?= e($c['name']) ?>">
                                        <?php else: ?>
                                        <span class="vo-avatar__initials"><?= e($c['initials']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="vo-conversation__body">
                                        <span class="vo-conversation__row">
                                            <span class="vo-conversation__name"><?= e($c['name']) ?></span>
                                            <span class="vo-conversation__time<?= $c['unread'] > 0 ? ' vo-conversation__time--unread' : '' ?>"><?= e(contact_time($c['last_time'])) ?></span>
                                        </span>
                                        <span class="vo-conversation__row">
                                            <span class="vo-conversation__preview<?= $c['unread'] > 0 ? ' vo-conversation__preview--unread' : '' ?>"><?= e($c['last_message'] ? mb_strimwidth($c['last_message'], 0, 60, '…') : contact_role_label($c)) ?></span>
                                            <?php if ($c['unread'] > 0 && !($active && $c['id'] === $active['id'])): ?>
                                            <span class="vo-unread-count"><?= $c['unread'] ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </span>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>

                    <!-- Main chat area -->
                    <section class="vo-chat__main">
                        <?php if (!$active): ?>
                        <div class="vo-chat__feed"><div class="vo-date-divider"><span>Select a conversation</span></div></div>
                        <?php else: ?>
                        <header class="vo-chat__header">
                            <div class="vo-chat__header-who">
                                <span class="vo-avatar vo-avatar--lg">
                                    <?php if ($active['avatar']): ?>
                                    <img src="<?= e($active['avatar']) ?>" alt="<?= e($active['name']) ?>">
                                    <?php else: ?>
                                    <span class="vo-avatar__initials"><?= e($active['initials']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <div class="vo-chat__header-name">
                                        <h1><?= e($active['name']) ?></h1>
                                        <span class="vo-role-badge"><?= e(contact_role_label($active)) ?></span>
                                    </div>
                                    <p class="vo-chat__header-meta"><i class="fa-solid fa-car"></i> AutoCare workshop team</p>
                                </div>
                            </div>
                            <!-- Static: phone/info actions (staff phone numbers are not shared with customers) -->
                            <div class="vo-chat__header-actions">
                                <button type="button" class="icon-btn" aria-label="Call" disabled><i class="fa-solid fa-phone"></i></button>
                                <button type="button" class="icon-btn" aria-label="Conversation info" disabled><i class="fa-solid fa-circle-info"></i></button>
                            </div>
                        </header>

                        <div class="vo-chat__feed" id="vo-chat-feed" aria-live="polite">
                            <div class="vo-date-divider"><span>Loading messages…</span></div>
                        </div>

                        <form class="vo-chat__input" id="vo-chat-form">
                            <div class="vo-chat__input-row">
                                <button type="button" class="icon-btn" data-unsupported aria-label="Attach a file"><i class="fa-solid fa-paperclip"></i></button>
                                <button type="button" class="icon-btn" data-unsupported aria-label="Attach an image"><i class="fa-regular fa-image"></i></button>
                                <textarea rows="1" maxlength="2000" placeholder="Type a message..." aria-label="Type a message"></textarea>
                                <button type="submit" class="vo-send-btn" aria-label="Send message"><i class="fa-solid fa-paper-plane"></i></button>
                            </div>
                            <p class="vo-chat__input-hint">Press Enter to send, Shift + Enter for new line</p>
                        </form>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/shared/autocare-chat.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-chat.js"></script>
</body>

</html>
