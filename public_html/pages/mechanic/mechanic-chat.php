<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/chat-common.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$contacts = chat_contacts($pdo, $mechanicId, 'Mechanic');
$active = null;
$wanted = input_id($_GET['contact_id'] ?? null);
foreach ($contacts as $c) {
    if ($c['id'] === $wanted) {
        $active = $c;
    }
}
$active = $active ?? ($contacts[0] ?? null);

function avatar_color(string $role): string
{
    return ['Manager' => 'chat-avatar--navy', 'VehicleOwner' => 'chat-avatar--blue'][$role] ?? 'chat-avatar--gray';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Chats</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-chat.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/default-sidebar-topbar-style.css">
</head>

<body>
    <div class="app">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="brand">
                <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
                <span class="brand-name">
                    <span style="color: #ffffff;">Auto</span><span style="color: #f97316;">Care</span>
                </span>
            </div>

            <!-- Nav Bar -->
            <nav class="nav">
                <a href="mechanic-dashboard.php" class="nav-item"><i class="fa-solid fa-gauge"></i> Dashboard</a>
                <a href="mechanic-assigned-jobs.php" class="nav-item"><i class="fa-solid fa-clipboard-list"></i> Assigned Jobs</a>
                <a href="mechanic-workflow.php" class="nav-item"><i class="fa-solid fa-diagram-project"></i> Progress</a>
                <a href="mechanic-parts-labor.php" class="nav-item"><i class="fa-solid fa-toolbox"></i> Parts &amp; Labor</a>
                <a href="mechanic-repair-photos.php" class="nav-item"><i class="fa-solid fa-camera"></i> Update Photos</a>
                <a href="mechanic-fault-report.php" class="nav-item"><i class="fa-solid fa-triangle-exclamation"></i> Report Faults</a>
                <a href="mechanic-chat.php" class="nav-item active"><i class="fa-solid fa-comments"></i> Chats</a>
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
                <div class="chat-layout" data-contact-id="<?= $active ? (int) $active['id'] : '' ?>" data-contact-name="<?= e($active['name'] ?? '') ?>" data-contact-initials="<?= e($active['initials'] ?? '') ?>" data-contact-color="<?= $active ? avatar_color($active['role']) : '' ?>">

                    <!-- Conversations list -->
                    <aside class="chat-list">
                        <div class="chat-list__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="chat-search" placeholder="Search chats..." aria-label="Search chats">
                        </div>

                        <nav class="chat-list__items">
                            <?php if (empty($contacts)): ?>
                            <p style="padding: 16px; color: #757684;">No conversations yet.</p>
                            <?php endif; ?>
                            <?php foreach ($contacts as $c):
                                $isActive = $active && $c['id'] === $active['id'];
                                $unread = $c['unread'] > 0 && !$isActive;
                            ?>
                            <a href="mechanic-chat.php?contact_id=<?= (int) $c['id'] ?>" class="chat-item<?= $isActive ? ' chat-item--active' : '' ?>" data-contact-id="<?= (int) $c['id'] ?>" data-contact-name="<?= e($c['name']) ?>">
                                <span class="chat-avatar <?= avatar_color($c['role']) ?>"><?= e($c['initials']) ?></span>
                                <div class="chat-item__body">
                                    <div class="chat-item__top">
                                        <h3 class="chat-item__name"><?= e($c['name']) ?> <span style="font-weight: 400; color: #757684;">(<?= e(contact_role_label($c)) ?>)</span></h3>
                                        <span class="chat-item__time<?= $unread ? ' chat-item__time--unread' : '' ?>"><?= e(contact_time($c['last_time'])) ?></span>
                                    </div>
                                    <p class="chat-item__preview<?= $unread ? ' chat-item__preview--unread' : '' ?>"><?= e($c['last_message'] ? mb_strimwidth($c['last_message'], 0, 60, '…') : 'No messages yet') ?></p>
                                </div>
                                <?php if ($unread): ?>
                                <span class="chat-item__dot" aria-label="<?= $c['unread'] ?> unread"></span>
                                <?php endif; ?>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </aside>

                    <!-- Chat thread -->
                    <section class="chat-thread">
                        <?php if (!$active): ?>
                        <div class="chat-thread__messages"><div class="chat-date-divider"><span>Select a conversation</span></div></div>
                        <?php else: ?>
                        <header class="chat-thread__header">
                            <div class="chat-thread__who">
                                <span class="chat-avatar <?= avatar_color($active['role']) ?> chat-avatar--lg"><?= e($active['initials']) ?></span>
                                <div class="chat-thread__info">
                                    <h2><?= e($active['name']) ?></h2>
                                    <p><?= e(contact_role_label($active)) ?></p>
                                </div>
                            </div>
                            <!-- Static: call/options actions have no backing data -->
                            <div class="chat-thread__actions">
                                <button type="button" class="chat-icon-btn" aria-label="Call" disabled><i class="fa-solid fa-phone"></i></button>
                                <button type="button" class="chat-icon-btn" aria-label="More options" disabled><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            </div>
                        </header>

                        <div class="chat-thread__messages" id="chat-feed" aria-live="polite">
                            <div class="chat-date-divider"><span>Loading messages…</span></div>
                        </div>

                        <div class="chat-input-area">
                            <div class="chat-input-bar">
                                <button type="button" class="chat-input-btn" data-unsupported aria-label="Attach file"><i class="fa-solid fa-paperclip"></i></button>
                                <textarea id="chat-input" placeholder="Type a message..." rows="1" maxlength="2000" aria-label="Type a message"></textarea>
                                <button type="button" class="chat-input-btn" data-unsupported aria-label="Add emoji"><i class="fa-regular fa-face-smile"></i></button>
                                <button type="button" class="chat-input-send" id="chat-send" aria-label="Send message"><i class="fa-solid fa-paper-plane"></i></button>
                            </div>
                            <p class="chat-input-hint">Press Enter to send, Shift+Enter for new line</p>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/shared/autocare-chat.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-chat.js"></script>
</body>

</html>
