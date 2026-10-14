<?php
require_once __DIR__ . '/../../api/db.php';

$stmt = $pdo->query("
    SELECT e.estimate_id as id,
           e.code,
           e.job_id as job_card_id,
           COALESCE(j.code, CONCAT('JC-', e.job_id)) as job_card_code,
           COALESCE(j.service_text, 'General Repair') as job_card_title,
           COALESCE(u.name, 'Customer') as customer_name,
           COALESCE(m.name, 'Workshop Manager') as mechanic_name,
           e.status,
           COALESCE(e.sent_date, '-') as sent_date,
           e.line_items,
           e.subtotal,
           e.tax_rate,
           e.tax_amount,
           e.total_estimated_cost
    FROM RepairEstimates e
    LEFT JOIN JobCards j ON e.job_id = j.job_id
    LEFT JOIN Appointments a ON j.appointment_id = a.appointment_id
    LEFT JOIN Users u ON a.owner_id = u.user_id
    LEFT JOIN Users m ON j.mechanic_id = m.user_id
    ORDER BY e.estimate_id DESC
");
$estimates = $stmt->fetchAll();
$firstEst = !empty($estimates) ? $estimates[0] : null;
$firstItems = json_decode($firstEst['line_items'] ?? '[]', true) ?: [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AutoCare - Cost Estimates</title>
  <link rel="stylesheet" href="../../assets/css/manager/Default-sidebar-topbar-style.css">
  <link rel="stylesheet" href="../../assets/css/manager/manager-cost-estimation.css">
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

        <a class="nav-item active" href="manager-cost-estimation.php">
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

        <!-- Page Heading -->
        <div class="page-heading">
          <div>
            <h1>Cost Estimates</h1>
            <p>Draft, review, and approve repair job cost calculations connected to MySQL</p>
          </div>

          <button class="new-estimate-btn">+ New Estimate</button>
        </div>


        <!-- MAIN TWO-COLUMN LAYOUT -->
        <div class="estimates-grid">

          <!-- Left Table Panel -->
          <div class="panel table-panel">
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>ESTIMATE ID</th>
                    <th>JOB CARD</th>
                    <th>CUSTOMER</th>
                    <th>MECHANIC</th>
                    <th>TOTAL COST</th>
                    <th>STATUS</th>
                    <th>SENT DATE</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($estimates)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #94a3b8;">No cost estimates found</td></tr>
                  <?php else: ?>
                    <?php foreach ($estimates as $idx => $est): 
                      $isSelected = ($idx === 0) ? 'selected' : '';
                      $badgeClass = 'badge-draft';
                      if ($est['status'] === 'Sent') $badgeClass = 'badge-sent';
                      elseif ($est['status'] === 'Send to Customer') $badgeClass = 'badge-send';
                    ?>
                    <tr class="<?= $isSelected ?>" data-est-id="<?= $est['id'] ?>" style="cursor: pointer;">
                      <td class="bold"><?= htmlspecialchars($est['code']) ?></td>
                      <td class="blue-link" onclick="event.stopPropagation(); window.location.href='manager-jobCards.php';"><?= htmlspecialchars($est['job_card_code']) ?></td>
                      <td><?= htmlspecialchars($est['customer_name']) ?></td>
                      <td><?= htmlspecialchars($est['mechanic_name']) ?></td>
                      <td class="bold">৳<?= number_format((float)$est['total_estimated_cost'], 2) ?></td>
                      <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($est['status']) ?></span></td>
                      <td><?= htmlspecialchars($est['sent_date']) ?></td>
                      <td class="action-cell">
                        <button class="icon-btn" title="View Options">
                          <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
                        </button>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Right Estimate Builder Panel -->
          <div class="panel builder-panel">

            <div class="builder-header">
              <div>
                <h2>Estimate Builder</h2>
                <span class="builder-subtitle"><?= htmlspecialchars($firstEst['code'] ?? 'EST-NEW') ?> (<?= strtoupper($firstEst['status'] ?? 'DRAFT') ?>)</span>
              </div>
              <button class="close-btn">&times;</button>
            </div>

            <!-- Mechanic Info Banner -->
            <div class="info-banner">
              <svg viewBox="0 0 24 24">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
              </svg>
              <span>Initial Estimate by: <?= htmlspecialchars($firstEst['mechanic_name'] ?? 'Workshop Manager') ?></span>
            </div>

            <!-- Linked Job Card -->
            <div class="linked-card-section">
              <label class="section-label">Linked Job Card</label>
              <div class="linked-card-box" onclick="window.location.href='manager-jobCards.php'" style="cursor: pointer;">
                <svg viewBox="0 0 24 24">
                  <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                  <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <span><?= htmlspecialchars(($firstEst['job_card_code'] ?? 'JC') . ' - ' . ($firstEst['job_card_title'] ?? 'Repair')) ?></span>
              </div>
            </div>

            <!-- Line Items Header -->
            <div class="line-items-header">
              <span class="section-label">Line Items</span>
              <button class="add-item-btn">
                <span>⊕</span> Add Item
              </button>
            </div>

            <!-- Line Items Table -->
            <div class="items-table-wrapper">
              <table class="items-table">
                <thead>
                  <tr>
                    <th>ITEM DESCRIPTION</th>
                    <th class="text-center">QTY/HRS</th>
                    <th class="text-right">PRICE</th>
                    <th class="text-right">TOTAL</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody id="estimate-line-items-tbody">
                  <?php foreach ($firstItems as $idx => $item): ?>
                  <tr data-item-index="<?= $idx ?>">
                    <td><input type="text" class="item-desc" value="<?= htmlspecialchars($item['description'] ?? '') ?>"></td>
                    <td class="text-center"><input type="number" class="item-qty" value="<?= $item['hours_or_qty'] ?? 1 ?>" style="width: 50px;"></td>
                    <td class="text-right"><input type="number" class="item-price" value="<?= $item['unit_price'] ?? 0 ?>" style="width: 70px;"></td>
                    <td class="text-right bold item-total">৳<?= number_format((float)($item['total'] ?? 0), 2) ?></td>
                    <td><button class="del-row-btn">&times;</button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Totals Section -->
            <div class="totals-section">
              <div class="total-row">
                <span>Subtotal</span>
                <span class="subtotal-val">৳<?= number_format((float)($firstEst['subtotal'] ?? 0), 2) ?></span>
              </div>
              <div class="total-row">
                <span>Estimated Tax (8.5%)</span>
                <span class="tax-val">৳<?= number_format((float)($firstEst['tax_amount'] ?? 0), 2) ?></span>
              </div>
              <div class="total-row grand-total">
                <strong>Total Estimate</strong>
                <strong class="total-val">৳<?= number_format((float)($firstEst['total_estimated_cost'] ?? 0), 2) ?></strong>
              </div>
            </div>

            <!-- Builder Action Buttons -->
            <div class="builder-actions">
              <button class="btn btn-outline" id="btn-save-draft">Save Draft</button>
              <button class="btn btn-primary" id="btn-send-estimate">Send to Customer</button>
            </div>

          </div>

        </div>

      </section>
    </main>

  </div>

  <script src="../../assets/js/manager/manager-store.js"></script>
  <script src="../../assets/js/manager/manager-common.js"></script>
  <script src="../../assets/js/manager/manager-cost-estimation.js"></script>
</body>

</html>
