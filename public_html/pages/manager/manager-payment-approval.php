<?php
require_once __DIR__ . '/../../api/db.php';

try {
    // 1. Inventory stats
    $stmt = $pdo->query("SELECT COALESCE(SUM(quantity_in_stock), 0) FROM SpareParts");
    $totalInStock = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM SpareParts WHERE quantity_in_stock <= low_stock_threshold");
    $lowStockCount = (int) $stmt->fetchColumn();

    // 2. Request counts
    $stmt = $pdo->query("SELECT COUNT(*), COALESCE(SUM(total_price), 0) FROM JobParts WHERE status = 'Pending Approval'");
    $pendingStats = $stmt->fetch(PDO::FETCH_NUM);
    $pendingCount = (int) $pendingStats[0];
    $todayValue = (float) $pendingStats[1];

    $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Approved'");
    $approvedCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts WHERE status = 'Rejected'");
    $rejectedCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM JobParts");
    $allCount = (int) $stmt->fetchColumn();

    // 3. Request list (Pending by default or all)
    $sql = "
        SELECT jp.job_part_id as id,
               jp.job_id as job_card_id,
               COALESCE(j.work_order, CONCAT('#WO-', 2000 + jp.job_id)) as work_order,
               jp.part_id,
               COALESCE(sp.name, 'Spare Part') as part_name,
               CONCAT('PN: ', COALESCE(sp.sku, 'PN-UNKNOWN')) as part_number,
               jp.quantity,
               COALESCE(sp.unit, 'Units') as unit,
               jp.unit_price,
               COALESCE(jp.total_price, jp.quantity * jp.unit_price) as total_price,
               COALESCE(m.name, 'Workshop Mechanic') as mechanic_name,
               jp.status,
               jp.rejection_reason,
               DATE_FORMAT(jp.requested_at, '%b %d, %Y %H:%i') as requested_at_formatted
        FROM JobParts jp
        LEFT JOIN JobCards j ON jp.job_id = j.job_id
        LEFT JOIN SpareParts sp ON jp.part_id = sp.part_id
        LEFT JOIN Users m ON j.mechanic_id = m.user_id
        WHERE jp.status = 'Pending Approval'
        ORDER BY jp.job_part_id DESC
    ";
    $requests = $pdo->query($sql)->fetchAll();

} catch (Exception $e) {
    $totalInStock = 0;
    $lowStockCount = 0;
    $pendingCount = 0;
    $todayValue = 0;
    $approvedCount = 0;
    $rejectedCount = 0;
    $allCount = 0;
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Spare Parts Approval</title>
  <link rel="stylesheet" href="../../assets/css/manager/manager-payment-approval.css">
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
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

        <a class="nav-item active" href="manager-payment-approval.php">
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

      <!-- ================= CONTENT ================= -->
      <section class="content">

        <!-- ================= STATS CARDS ================= -->
        <div class="stats-grid">

          <!-- Card 1 -->
          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon icon-grey">
                <svg viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
              </div>
              <span class="badge-tag tag-grey">Total</span>
            </div>
            <div class="stat-sub-label">Available Parts</div>
            <div class="stat-value"><?= number_format($totalInStock) ?></div>
          </div>

          <!-- Card 2 -->
          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon icon-red">
                <svg viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
              </div>
              <span class="badge-tag tag-red">Action Required</span>
            </div>
            <div class="stat-sub-label">Low Stock Alerts</div>
            <div class="stat-value-group">
              <span class="stat-value"><?= $lowStockCount ?></span>
              <span class="stat-trend-sub">Live DB</span>
            </div>
          </div>

          <!-- Card 3 -->
          <div class="stat-card">
            <div class="stat-header">
              <div class="stat-icon icon-grey">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg>
              </div>
              <span class="badge-tag tag-blue">Value: ৳<?= number_format($todayValue) ?></span>
            </div>
            <div class="stat-sub-label">Pending Requests</div>
            <div class="stat-value-group">
              <span class="stat-value"><?= $pendingCount ?></span>
              <span class="stat-value-sub">Value: ৳<?= number_format($todayValue) ?></span>
            </div>
          </div>

        </div>

        <!-- ================= APPROVAL TABLE CARD ================= -->
        <div class="table-card">

          <div class="table-header">
            <div>
              <h2 id="approval-card-title">Pending Approvals</h2>
              <p id="approval-card-sub">Review and authorize mechanic part requests from database.</p>
            </div>

            <div class="header-actions">
              <div class="table-search-box">
                <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" id="approval-search-input" placeholder="Search part, mechanic, job...">
              </div>

              <button class="btn-new-request" id="btn-open-request-modal">
                <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2.2;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                + New Request
              </button>

              <button class="btn-secondary" id="btn-filter-modal">
                <svg viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                Filter
              </button>

              <button class="btn-approve-all" id="btn-approve-all-requests">
                <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                Approve All
              </button>

              <button class="btn-reset-data" id="btn-reset-sample-data" title="Reset sample requests data">
                <svg viewBox="0 0 24 24" style="width: 13px; height: 13px; stroke: currentColor; fill: none; stroke-width: 2;"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>
                Reset
              </button>
            </div>
          </div>

          <!-- Status Filter Tabs -->
          <div class="approval-tabs" id="approval-status-tabs">
            <button class="tab-btn active" data-status="Pending Approval">
              Pending Approval <span class="tab-count" id="badge-count-pending"><?= $pendingCount ?></span>
            </button>
            <button class="tab-btn" data-status="Approved">
              Approved <span class="tab-count" id="badge-count-approved"><?= $approvedCount ?></span>
            </button>
            <button class="tab-btn" data-status="Rejected">
              Rejected <span class="tab-count" id="badge-count-rejected"><?= $rejectedCount ?></span>
            </button>
            <button class="tab-btn" data-status="all">
              All Requests <span class="tab-count" id="badge-count-all"><?= $allCount ?></span>
            </button>
          </div>

          <div class="table-container">
            <table>
              <thead>
                <tr>
                  <th>PART DETAILS</th>
                  <th>QUANTITY</th>
                  <th>UNIT PRICE</th>
                  <th>TOTAL</th>
                  <th>MECHANIC / JOB</th>
                  <th>STATUS</th>
                  <th class="actions-th">ACTIONS</th>
                </tr>
              </thead>
              <tbody id="approval-table-body">
                <?php if (empty($requests)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 36px; color: #94a3b8;">
                    No pending part requests in database.
                  </td>
                </tr>
                <?php else: ?>
                <?php foreach ($requests as $r): 
                  $parts = explode(' ', $r['mechanic_name']);
                  $initials = count($parts) > 1 ? strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1)) : strtoupper(substr($r['mechanic_name'], 0, 2));
                ?>
                <tr data-request-id="<?= $r['id'] ?>">
                  <td>
                    <div class="cell-main bold"><?= htmlspecialchars($r['part_name']) ?></div>
                    <div class="cell-sub"><?= htmlspecialchars($r['part_number']) ?></div>
                  </td>
                  <td>
                    <div class="cell-main"><?= $r['quantity'] ?> <?= htmlspecialchars($r['unit']) ?></div>
                  </td>
                  <td>
                    <div class="cell-main">৳<?= number_format($r['unit_price']) ?></div>
                  </td>
                  <td>
                    <div class="cell-main bold">৳<?= number_format($r['total_price']) ?></div>
                  </td>
                  <td>
                    <div class="user-cell">
                      <div class="avatar-small"><?= $initials ?></div>
                      <div>
                        <div class="cell-main"><?= htmlspecialchars($r['mechanic_name']) ?></div>
                        <div class="cell-sub-link">
                          <a href="manager-jobCards.php?id=<?= $r['job_card_id'] ?>" style="color: #f97316; text-decoration: none;">
                            <?= htmlspecialchars($r['work_order']) ?>
                          </a>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="badge-status badge-pending"><?= htmlspecialchars($r['status']) ?></span>
                  </td>
                  <td class="action-cell">
                    <button class="btn-action btn-approve" onclick="handleApprove(<?= $r['id'] ?>)" title="Approve Request">Approve</button>
                    <button class="btn-action btn-reject" onclick="handleReject(<?= $r['id'] ?>)" title="Reject Request">Reject</button>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="table-footer">
            <span class="showing-text" id="approval-showing-text">Showing <?= count($requests) ?> requests</span>
            <div class="pagination-arrows">
              <button class="arrow-btn" id="pagination-prev">&lt;</button>
              <button class="arrow-btn" id="pagination-next">&gt;</button>
            </div>
          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-payment-approval.js"></script>
</body>
</html>
