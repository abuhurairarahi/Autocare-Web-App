<?php
require_once __DIR__ . '/../../api/db.php';

$managerId = 2; // Alex Johnson
$activeContactId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 8; // Mike Davis default

try {
    // 1. Fetch available contacts (threads)
    $contactsStmt = $pdo->query("
        SELECT u.user_id as id,
               u.name,
               u.role,
               COALESCE(u.specialty, IF(u.role='Mechanic', 'Bay Tech', 'Client')) as role_title,
               COALESCE(u.avatar, 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150') as avatar,
               COALESCE(
                   (SELECT m2.message_text FROM ChatMessages m2 
                    WHERE (m2.sender_id = u.user_id AND m2.receiver_id = 2) 
                       OR (m2.sender_id = 2 AND m2.receiver_id = u.user_id) 
                    ORDER BY m2.created_at DESC LIMIT 1),
                   'No messages yet'
               ) as last_message,
               COALESCE(
                   (SELECT DATE_FORMAT(m2.created_at, '%h:%i %p') FROM ChatMessages m2 
                    WHERE (m2.sender_id = u.user_id AND m2.receiver_id = 2) 
                       OR (m2.sender_id = 2 AND m2.receiver_id = u.user_id) 
                    ORDER BY m2.created_at DESC LIMIT 1),
                   '10:00 AM'
               ) as last_time
        FROM Users u
        WHERE u.user_id != 2 AND u.role IN ('Mechanic', 'VehicleOwner')
        ORDER BY u.role ASC, u.name ASC
    ");
    $contacts = $contactsStmt->fetchAll();

    // 2. Fetch active contact info
    $activeContact = null;
    foreach ($contacts as $c) {
        if ($c['id'] == $activeContactId) {
            $activeContact = $c;
            break;
        }
    }
    if (!$activeContact && !empty($contacts)) {
        $activeContact = $contacts[0];
        $activeContactId = $activeContact['id'];
    }

    // 3. Fetch messages between manager and active contact
    $msgStmt = $pdo->prepare("
        SELECT m.message_id as id,
               m.sender_id,
               m.receiver_id,
               u.name as sender_name,
               COALESCE(u.avatar, 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80') as sender_avatar,
               COALESCE(m.job_tag, 'General') as job_tag,
               m.message_text as message,
               DATE_FORMAT(m.created_at, '%h:%i %p') as time,
               (m.sender_id != ?) as is_incoming,
               m.attachment_url
        FROM ChatMessages m
        JOIN Users u ON m.sender_id = u.user_id
        WHERE (m.sender_id = ? AND m.receiver_id = ?)
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.created_at ASC, m.message_id ASC
    ");
    $msgStmt->execute([$managerId, $managerId, $activeContactId, $activeContactId, $managerId]);
    $messages = $msgStmt->fetchAll();

} catch (Exception $e) {
    $contacts = [];
    $activeContact = null;
    $messages = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Messages</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-chat.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>

  <div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar">

      <a class="brand" href="manager-dashboard.php" style="text-decoration: none;">
        <span class="brand-icon"><i class="fa-solid fa-car"></i></span>
        <span class="brand-name">
          <span style="color: #ffffff;">Auto</span><span style="color: #f97316;">Care</span>
        </span>
      </a>

      <!-- Nav Bar -->
      <nav class="nav">
        <a class="nav-item" href="manager-dashboard.php">
          <span class="icon"><i class="fa-solid fa-border-all"></i></span>Dashboard
        </a>

        <a class="nav-item" href="manager-booking-request.php">
          <span class="icon"><i class="fa-regular fa-calendar-check"></i></span>Booking Requests
        </a>

        <a class="nav-item" href="manager-jobCards.php">
          <span class="icon"><i class="fa-regular fa-clipboard"></i></span>Job Cards
        </a>

        <a class="nav-item" href="manager-mechanics.php">
          <span class="icon"><i class="fa-solid fa-users-gear"></i></span>Mechanics
        </a>

        <a class="nav-item" href="manager-process-tracker.php">
          <span class="icon"><i class="fa-solid fa-list-check"></i></span>Job Tracking
        </a>

        <a class="nav-item" href="manager-cost-estimation.php">
          <span class="icon"><i class="fa-solid fa-calculator"></i></span>Cost Estimates
        </a>

        <a class="nav-item" href="manager-invoice-management.php">
          <span class="icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>Invoices
        </a>

        <a class="nav-item" href="manager-payment-approval.php">
          <span class="icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>Spare Parts Approval
        </a>

        <a class="nav-item active" href="manager-chat.php">
          <span class="icon"><i class="fa-regular fa-comments"></i></span>Chats
        </a>

        <a class="nav-item" href="manager-performance.php">
          <span class="icon"><i class="fa-solid fa-chart-line"></i></span>Report
        </a>
      </nav>

      <a class="logout" href="../login.html">
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
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
          </div>

          <div class="avatar">A</div>
        </div>
      </header>

      <!-- CHAT CONTAINER -->
      <section class="chat-container">

        <!-- Left Threads Panel -->
        <div class="threads-panel">
          <div class="threads-header">
            <h2>Messages</h2>
            <button class="btn-new-chat" title="New Message">
              <svg viewBox="0 0 24 24">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
              </svg>
            </button>
          </div>

          <!-- Category Filter Tabs -->
          <div class="tabs">
            <button class="tab active">All</button>
            <button class="tab">Internal</button>
            <button class="tab">Clients</button>
          </div>

          <!-- Threads List -->
          <div class="threads-list">

            <?php if (empty($contacts)): ?>
            <div style="padding: 20px; color: #94a3b8; text-align: center;">No contacts found</div>
            <?php else: ?>
            <?php foreach ($contacts as $contact): 
              $isActive = ($contact['id'] == $activeContactId);
            ?>
            <div class="thread-item <?= $isActive ? 'active' : '' ?>" data-user-id="<?= $contact['id'] ?>" onclick="window.location.href='manager-chat.php?user_id=<?= $contact['id'] ?>'">
              <img class="avatar-img"
                src="<?= htmlspecialchars($contact['avatar']) ?>"
                alt="<?= htmlspecialchars($contact['name']) ?>">
              <div class="thread-content">
                <div class="thread-top">
                  <span class="user-name"><?= htmlspecialchars($contact['name']) ?> <span class="role">(<?= htmlspecialchars($contact['role_title']) ?>)</span></span>
                  <span class="time"><?= htmlspecialchars($contact['last_time']) ?></span>
                </div>
                <div class="thread-preview">
                  <span class="text"><?= htmlspecialchars($contact['last_message']) ?></span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

          </div>
        </div>

        <!-- Right Main Chat Window -->
        <div class="chat-window">

          <!-- Active Chat Header -->
          <div class="chat-header">
            <div class="chat-user-info">
              <div class="avatar-wrapper">
                <img class="avatar-img"
                  src="<?= htmlspecialchars($activeContact['avatar'] ?? 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=80&q=80') ?>"
                  alt="<?= htmlspecialchars($activeContact['name'] ?? 'User') ?>">
              </div>
              <div>
                <h3 class="user-title"><?= htmlspecialchars($activeContact['name'] ?? 'Chat') ?></h3>
                <div class="status-online">
                  <span class="dot"></span> Online
                </div>
              </div>
            </div>

            <div class="chat-header-actions">
              <div class="job-chip">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                Job #8492
              </div>
              <button class="more-btn">
                <svg viewBox="0 0 24 24">
                  <circle cx="12" cy="5" r="1.5"></circle>
                  <circle cx="12" cy="12" r="1.5"></circle>
                  <circle cx="12" cy="19" r="1.5"></circle>
                </svg>
              </button>
            </div>
          </div>

          <!-- Chat Conversation View -->
          <div class="chat-messages">

            <div class="date-divider">
              <span>DATABASE CONVERSATION</span>
            </div>

            <?php if (empty($messages)): ?>
            <div style="text-align: center; color: #94a3b8; padding: 40px;">No messages yet. Send a message below to start the conversation!</div>
            <?php else: ?>
            <?php foreach ($messages as $msg): 
              $isIncoming = (bool)$msg['is_incoming'];
              $attachments = [];
              if (!empty($msg['attachment_url'])) {
                  $dec = json_decode($msg['attachment_url'], true);
                  $attachments = is_array($dec) ? $dec : [$msg['attachment_url']];
              }
            ?>
            <div class="message <?= $isIncoming ? 'incoming' : 'outgoing' ?>">
              <?php if ($isIncoming): ?>
              <img class="avatar-img"
                src="<?= htmlspecialchars($msg['sender_avatar']) ?>"
                alt="<?= htmlspecialchars($msg['sender_name']) ?>">
              <?php endif; ?>

              <div class="message-body">
                <div class="message-sender">
                  <?= $isIncoming ? htmlspecialchars($msg['sender_name']) . ' <span class="msg-time">' . htmlspecialchars($msg['time']) . '</span>' : '<span class="msg-time">' . htmlspecialchars($msg['time']) . '</span> You' ?>
                </div>
                <div class="bubble">
                  <?= htmlspecialchars($msg['message']) ?>
                </div>

                <?php if (!empty($attachments)): ?>
                <div class="media-attachments">
                  <?php foreach ($attachments as $att): ?>
                  <img src="<?= htmlspecialchars($att) ?>" alt="Attachment">
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
              </div>

              <?php if (!$isIncoming): ?>
              <img class="avatar-img"
                src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=80&q=80"
                alt="You">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

          </div>

          <!-- Chat Input Footer -->
          <div class="chat-input-area">
            <div class="composer-box">
              <div class="attachment-actions">
                <button class="attach-btn" title="Attach file">
                  <svg viewBox="0 0 24 24">
                    <path
                      d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48">
                    </path>
                  </svg>
                </button>
                <button class="attach-btn" title="Add image">
                  <svg viewBox="0 0 24 24">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                  </svg>
                </button>
              </div>

              <input type="text" placeholder="Type a message to <?= htmlspecialchars($activeContact['name'] ?? 'User') ?>...">

              <button class="send-btn" title="Send message">
                <svg viewBox="0 0 24 24">
                  <line x1="22" y1="2" x2="11" y2="13"></line>
                  <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                </svg>
              </button>
            </div>

            <div class="composer-hint">PRESS ENTER TO SEND, SHIFT+ENTER FOR NEW LINE</div>
          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-chat.js"></script>
</body>

</html>
