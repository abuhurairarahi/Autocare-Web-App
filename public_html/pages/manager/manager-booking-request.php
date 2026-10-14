<?php
require_once __DIR__ . '/../../api/db.php';

$stmt = $pdo->query("
    SELECT a.appointment_id as id,
           COALESCE(a.code, CONCAT('BRQ-2026-', LPAD(a.appointment_id, 3, '0'))) as code,
           a.owner_id,
           u.name as customer_name,
           u.phone as customer_phone,
           u.email as customer_email,
           a.vehicle_id,
           v.make,
           v.model as vehicle_model,
           v.license_plate,
           v.vin,
           a.service_category_id as category_id,
           COALESCE(sc.name, 'General Service') as category_name,
           a.preferred_date,
           DATE_FORMAT(a.preferred_date, '%b %d, %H:%i') as formatted_date,
           a.issue_description as description,
           COALESCE(a.priority, 'Normal') as priority,
           a.status,
           a.created_at
    FROM Appointments a
    JOIN Users u ON a.owner_id = u.user_id
    JOIN Vehicles v ON a.vehicle_id = v.vehicle_id
    LEFT JOIN ServiceCategories sc ON a.service_category_id = sc.category_id
    WHERE a.status = 'Pending'
    ORDER BY a.appointment_id DESC
");
$pendingRequests = $stmt->fetchAll();
$firstReq = !empty($pendingRequests) ? $pendingRequests[0] : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Service Booking Requests</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-booking-request.css">
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

        <a class="nav-item active" href="manager-booking-request.php">
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

        <a class="nav-item" href="manager-chat.php">
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

      <!-- CONTENT -->
      <section class="content">

        <!-- Page Header & Action Controls -->
        <div class="page-heading">
          <div>
            <h1>Service Booking Requests</h1>
            <p>Manage incoming service and repair requests from fleet managers and retail customers connected to MySQL.</p>
          </div>

          <div class="action-buttons">
            <button class="btn-outline">
              <svg viewBox="0 0 24 24">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
              </svg>
              Filter
            </button>

            <button class="btn-outline">
              <svg viewBox="0 0 24 24">
                <line x1="11" y1="5" x2="11" y2="19"></line>
                <line x1="18" y1="9" x2="18" y2="19"></line>
                <line x1="4" y1="13" x2="4" y2="19"></line>
              </svg>
              Sort
            </button>

            <button class="btn-outline">
              <svg viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                <polyline points="7 10 12 15 17 10"></polyline>
                <line x1="12" y1="15" x2="12" y2="3"></line>
              </svg>
              Export
            </button>
          </div>
        </div>


        <!-- MAIN LAYOUT GRID -->
        <div class="booking-grid">

          <!-- Left Table Card -->
          <div class="panel table-panel">
            <div class="panel-header">
              <h2>Pending Requests</h2>
              <span class="badge-count"><?= count($pendingRequests) ?> New</span>
            </div>

            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>BOOKING ID</th>
                    <th>OWNER / FLEET</th>
                    <th>VEHICLE</th>
                    <th>SERVICE</th>
                    <th>DATE</th>
                    <th>PRIORITY</th>
                    <th>STATUS</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($pendingRequests)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No pending booking requests</td></tr>
                  <?php else: ?>
                    <?php foreach ($pendingRequests as $idx => $r): 
                      $isSelected = ($idx === 0) ? 'selected' : '';
                      $pClass = (strtolower($r['priority']) === 'high') ? 'high' : 'normal';
                    ?>
                    <tr class="<?= $isSelected ?>" data-request-id="<?= $r['id'] ?>" style="cursor: pointer;">
                      <td class="bold"><?= htmlspecialchars($r['code']) ?></td>
                      <td><?= htmlspecialchars($r['customer_name']) ?></td>
                      <td>
                        <div class="vehicle-cell">
                          <span><?= htmlspecialchars($r['vehicle_model']) ?></span>
                          <small><?= htmlspecialchars($r['license_plate']) ?></small>
                        </div>
                      </td>
                      <td><?= htmlspecialchars($r['category_name']) ?></td>
                      <td><?= htmlspecialchars($r['formatted_date']) ?></td>
                      <td><span class="badge-priority <?= $pClass ?>"><?= htmlspecialchars($r['priority']) ?></span></td>
                      <td><span class="badge-status">Pending Review</span></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Details Card -->
          <div class="panel details-panel">

            <div class="details-header">
              <span class="subtitle">Request Details</span>
              <h2><?= htmlspecialchars($firstReq['code'] ?? 'BRQ-PENDING') ?></h2>
              <p class="submitted-date">Submitted <?= htmlspecialchars($firstReq['formatted_date'] ?? 'Recently') ?></p>
            </div>

            <!-- Owner Information Box -->
            <div class="info-card">
              <span class="card-label">OWNER INFORMATION</span>
              <div class="owner-details">
                <div class="avatar-box"><?= strtoupper(substr($firstReq['customer_name'] ?? 'AL', 0, 2)) ?></div>
                <div class="owner-text">
                  <h3><?= htmlspecialchars($firstReq['customer_name'] ?? 'Customer') ?></h3>
                  <p><svg viewBox="0 0 24 24">
                      <path
                        d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z">
                      </path>
                    </svg> <?= htmlspecialchars($firstReq['customer_phone'] ?? 'N/A') ?></p>
                </div>
              </div>
            </div>

            <!-- Vehicle Profile Box -->
            <div class="info-card">
              <span class="card-label">VEHICLE PROFILE</span>
              <div class="vehicle-details">
                <div class="row">
                  <span>Model:</span>
                  <strong><?= htmlspecialchars($firstReq['vehicle_model'] ?? 'Vehicle') ?></strong>
                </div>
                <div class="row">
                  <span>Plate Number:</span>
                  <strong><?= htmlspecialchars($firstReq['license_plate'] ?? 'N/A') ?></strong>
                </div>
                <div class="row">
                  <span>VIN:</span>
                  <strong><?= htmlspecialchars($firstReq['vin'] ?? 'N/A') ?></strong>
                </div>
              </div>
            </div>

            <!-- Reported Issue / Scope Box -->
            <div class="info-card">
              <span class="card-label">REPORTED ISSUE & SCOPE</span>
              <p class="issue-text">
                <?= htmlspecialchars($firstReq['description'] ?? 'Standard repair request.') ?>
              </p>
            </div>

            <!-- Action Buttons Footer -->
            <div class="details-actions">
              <button class="btn btn-outline btn-reject" id="btn-reject-request">Decline Request</button>
              <button class="btn btn-primary btn-approve" id="btn-approve-request" style="background-color: blue; color: white; font-weight: bold; padding: 4px 8px;">Approve & create job card</button>
            </div>

          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-booking-request.js"></script>
</body>

</html>
