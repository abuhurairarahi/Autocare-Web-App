<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/vehicleowner/owner-data.php';

$user = require_page_role('VehicleOwner');
$ownerId = $user['id'];

$estimates = owner_estimates($pdo, $ownerId);
$awaiting = array_filter($estimates, fn ($est) => $est['awaiting_approval']);
$approvedCount = count(array_filter($estimates, fn ($est) => $est['status'] === 'Approved'));
$awaitingValue = array_sum(array_column($awaiting, 'total_estimated_cost'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare - Repair Estimates</title>
    <link rel="stylesheet" href="../../assets/css/vehicleowner/vehicleowner-repair-estimates.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/vehicleowner-sidebar-topbar-style.css">
    <style>.vo-estimate[hidden] { display: none; }</style>
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
                <a href="vehicleowner-repair-estimates.php" class="nav-item active"><i class="fa-solid fa-file-invoice-dollar"></i> Repair Estimates</a>
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
                        <nav class="vo-breadcrumb" aria-label="Breadcrumb">
                            <a href="vehicleowner-dashboard.php">Dashboard</a>
                            <i class="fa-solid fa-chevron-right"></i>
                            <span>Repair Estimates</span>
                        </nav>
                        <h1>Repair Estimates</h1>
                    </div>

                    <!-- KPI row -->
                    <div class="vo-kpis">
                        <div class="vo-kpi">
                            <div>
                                <p class="vo-kpi__label">Pending Approvals</p>
                                <p class="vo-kpi__value"><?= count($awaiting) ?></p>
                            </div>
                            <span class="vo-kpi__icon vo-kpi__icon--pending"><i class="fa-solid fa-hourglass-half"></i></span>
                        </div>
                        <div class="vo-kpi">
                            <div>
                                <p class="vo-kpi__label">Approved Estimates</p>
                                <p class="vo-kpi__value"><?= $approvedCount ?></p>
                            </div>
                            <span class="vo-kpi__icon vo-kpi__icon--approved"><i class="fa-solid fa-circle-check"></i></span>
                        </div>
                        <div class="vo-kpi vo-kpi--total">
                            <div>
                                <p class="vo-kpi__label">Awaiting Approval Value</p>
                                <p class="vo-kpi__value"><?= e(money($awaitingValue)) ?></p>
                            </div>
                            <span class="vo-kpi__icon vo-kpi__icon--total"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                        </div>
                    </div>

                    <!-- Estimates list -->
                    <div class="vo-estimates">
                        <?php if (empty($estimates)): ?>
                        <p style="color: #757684;">No estimates have been sent to you yet.</p>
                        <?php endif; ?>

                        <?php foreach ($estimates as $est):
                            $vehicleLabel = $est['year'] . ' ' . $est['make'] . ' ' . $est['model'];
                            $sent = $est['sent_date'] !== '-' && $est['sent_date'] ? $est['sent_date'] : date('M d, Y', strtotime($est['created_at']));
                        ?>
                        <?php if (!$est['awaiting_approval']): ?>
                        <!-- Collapsed estimate: click to expand the details below -->
                        <article class="vo-collapsed-estimate" data-toggle="est-<?= (int) $est['id'] ?>" role="button" tabindex="0" aria-expanded="false" aria-controls="est-<?= (int) $est['id'] ?>" style="cursor: pointer;">
                            <div class="vo-collapsed-estimate__left">
                                <span class="vo-collapsed-estimate__icon"><i class="fa-solid fa-wrench"></i></span>
                                <div>
                                    <div class="vo-estimate__title-row">
                                        <h2><?= e($est['code']) ?></h2>
                                        <span class="vo-badge vo-badge--approved"><?= e($est['status']) ?></span>
                                    </div>
                                    <p class="vo-estimate__meta"><?= e($vehicleLabel . ' | ' . $est['service_text']) ?></p>
                                </div>
                            </div>
                            <div class="vo-collapsed-estimate__right">
                                <span class="vo-estimate__amount"><?= e(money($est['total_estimated_cost'])) ?></span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                        </article>
                        <?php endif; ?>

                        <article class="vo-estimate" id="est-<?= (int) $est['id'] ?>" data-estimate-id="<?= (int) $est['id'] ?>"<?= $est['awaiting_approval'] ? '' : ' hidden' ?>>
                            <div class="vo-estimate__main">
                                <div class="vo-estimate__header">
                                    <div>
                                        <div class="vo-estimate__title-row">
                                            <h2><?= e($est['code']) ?></h2>
                                            <span class="vo-badge <?= $est['awaiting_approval'] ? 'vo-badge--awaiting' : 'vo-badge--approved' ?>"><?= $est['awaiting_approval'] ? 'Awaiting Approval' : e($est['status']) ?></span>
                                        </div>
                                        <p class="vo-estimate__meta">Vehicle: <strong><?= e($vehicleLabel) ?></strong> | Job: <?= e($est['job_code']) ?> | Date: <?= e($sent) ?></p>
                                    </div>
                                </div>

                                <div class="vo-table-wrap">
                                    <table class="vo-breakdown">
                                        <thead>
                                            <tr>
                                                <th>Item/Service</th>
                                                <th class="vo-num">Quantity</th>
                                                <th class="vo-num">Unit Price</th>
                                                <th class="vo-num">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($est['line_items'])): ?>
                                            <tr><td colspan="4" style="color: #757684;">No line items.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($est['line_items'] as $item):
                                                $qty = (float) ($item['hours_or_qty'] ?? $item['quantity'] ?? 1);
                                                $unitPrice = (float) ($item['unit_price'] ?? 0);
                                                $lineTotal = (float) ($item['total'] ?? $qty * $unitPrice);
                                            ?>
                                            <tr>
                                                <td><?= e($item['description'] ?? 'Item') ?></td>
                                                <td class="vo-num"><?= e(rtrim(rtrim(number_format($qty, 2), '0'), '.')) ?></td>
                                                <td class="vo-num"><?= e(money($unitPrice)) ?></td>
                                                <td class="vo-num vo-num--strong"><?= e(money($lineTotal)) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="vo-estimate__footer">
                                    <div class="vo-estimate__actions">
                                        <?php if ($est['awaiting_approval']): ?>
                                        <button type="button" class="vo-btn vo-btn--primary" data-action="approve">Approve Estimate</button>
                                        <button type="button" class="vo-btn vo-btn--link" data-action="clarify">Request Clarification</button>
                                        <?php else: ?>
                                        <button type="button" class="vo-btn vo-btn--link" data-action="clarify">Ask a Question</button>
                                        <?php endif; ?>
                                    </div>
                                    <dl class="vo-summary">
                                        <div class="vo-summary__row">
                                            <dt>Subtotal:</dt>
                                            <dd><?= e(money($est['subtotal'])) ?></dd>
                                        </div>
                                        <div class="vo-summary__row">
                                            <dt>Taxes (<?= e(rtrim(rtrim(number_format($est['tax_rate'] * 100, 2), '0'), '.')) ?>%):</dt>
                                            <dd><?= e(money($est['tax_amount'])) ?></dd>
                                        </div>
                                        <div class="vo-summary__row vo-summary__row--total">
                                            <dt>Total:</dt>
                                            <dd><?= e(money($est['total_estimated_cost'])) ?></dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <aside class="vo-estimate__notes">
                                <h3><i class="fa-regular fa-note-sticky"></i> Technician Notes</h3>
                                <div class="vo-notes__body">
                                    <p><?= e($est['fault_report'] ?: 'No technician notes for this job.') ?></p>
                                </div>
                                <?php if ($est['mechanic_name']): ?>
                                <div class="vo-technician">
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 9999px; background: #e9e7f0; font-weight: 700;"><?= e(initials($est['mechanic_name'])) ?></span>
                                    <div>
                                        <p class="vo-technician__name"><?= e($est['mechanic_name']) ?></p>
                                        <p class="vo-technician__role"><?= e($est['mechanic_specialty'] ?: 'Technician') ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </aside>
                        </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-common.js"></script>
    <script src="../../assets/js/vehicleowner/vehicleowner-repair-estimates.js"></script>
</body>

</html>
