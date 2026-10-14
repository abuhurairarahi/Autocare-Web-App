<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$openJobs = mechanic_jobs($pdo, $mechanicId, ['status' => 'open']);
$requestedId = input_id($_GET['job_id'] ?? null) ?: ($openJobs[0]['id'] ?? null);
$job = $requestedId ? mechanic_job($pdo, $mechanicId, $requestedId) : null;
$parts = $job ? job_parts($pdo, $job['id']) : [];
$labor = $job ? job_labor($pdo, $job['id']) : [];
$summary = parts_labor_summary($parts, $labor);
$canEdit = $job && $job['is_open'];

function hours_label(float $hours): string
{
    return rtrim(rtrim(number_format($hours, 2), '0'), '.') . ' hrs';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Parts &amp; Labor</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-parts-labor.css">
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
                <a href="mechanic-parts-labor.php" class="nav-item active"><i class="fa-solid fa-toolbox"></i> Parts &amp; Labor</a>
                <a href="mechanic-repair-photos.php" class="nav-item"><i class="fa-solid fa-camera"></i> Update Photos</a>
                <a href="mechanic-fault-report.php" class="nav-item"><i class="fa-solid fa-triangle-exclamation"></i> Report Faults</a>
                <a href="mechanic-chat.php" class="nav-item"><i class="fa-solid fa-comments"></i> Chats</a>
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
                <div class="pl-wrap" data-job-id="<?= $job ? (int) $job['id'] : '' ?>">

                    <!-- Page header -->
                    <div class="pl-header">
                        <div class="pl-header__text">
                            <h1>Parts &amp; Labor<?= $job ? ': ' . e($job['code']) : '' ?></h1>
                            <p><?= $job ? e(($job['vehicle'] ? $job['vehicle'] . ' · ' : '') . $job['service_text']) : 'Record components used and time spent on current task.' ?></p>
                        </div>
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <?php if (count($openJobs) > 1 || ($job && !$job['is_open'])): ?>
                            <select id="pl-job-switch" aria-label="Choose job" style="padding: 8px 10px; border-radius: 6px; border: 1px solid #c5c5d4; font: inherit;">
                                <?php if ($job && !$job['is_open']): ?>
                                <option value="<?= (int) $job['id'] ?>" selected><?= e($job['code']) ?> (<?= e($job['status']) ?>)</option>
                                <?php endif; ?>
                                <?php foreach ($openJobs as $oj): ?>
                                <option value="<?= (int) $oj['id'] ?>"<?= $job && $oj['id'] === $job['id'] ? ' selected' : '' ?>><?= e($oj['code'] . ' · ' . ($oj['vehicle'] ?: $oj['service_text'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                            <?php if ($job): ?>
                            <span class="pl-status"><?= e($job['status']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!$job): ?>
                    <section class="pl-card">
                        <p><?= isset($_GET['job_id']) ? 'This job does not exist or is not assigned to you.' : 'You have no open jobs to record parts or labor against.' ?> <a href="mechanic-assigned-jobs.php">Back to Assigned Jobs</a></p>
                    </section>
                    <?php else: ?>
                    <!-- Two column layout -->
                    <div class="pl-grid">

                        <!-- Left column: data entry -->
                        <div class="pl-col-left">

                            <!-- Spare parts -->
                            <section class="pl-card">
                                <div class="pl-card__head">
                                    <h2 class="pl-card__title"><i class="fa-solid fa-box-archive"></i> Spare Parts Recording</h2>
                                    <?php if ($canEdit): ?>
                                    <button type="button" class="pl-btn pl-btn--primary" id="pl-add-part"><i class="fa-solid fa-plus"></i> Add Part</button>
                                    <?php endif; ?>
                                </div>

                                <div class="pl-table-wrap">
                                    <table class="pl-table">
                                        <thead>
                                            <tr>
                                                <th>Part Name</th>
                                                <th>Part Number</th>
                                                <th class="ta-r">Qty</th>
                                                <th class="ta-r">Unit Price</th>
                                                <th class="ta-r">Total</th>
                                                <th class="ta-c">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($parts)): ?>
                                            <tr><td colspan="6" class="ta-c" style="color: #757684;">No parts recorded yet.</td></tr>
                                            <?php endif; ?>
                                            <?php foreach ($parts as $p):
                                                $pillColor = ['Approved' => '#065f46', 'Rejected' => '#93000a'][$p['status']] ?? '#92400e';
                                            ?>
                                            <tr data-job-part-id="<?= (int) $p['id'] ?>">
                                                <td class="pl-strong">
                                                    <?= e($p['name']) ?>
                                                    <span style="display: block; font-size: 11px; font-weight: 600; color: <?= $pillColor ?>;" title="<?= e($p['rejection_reason'] ?? '') ?>"><?= e($p['status']) ?></span>
                                                </td>
                                                <td><?= e($p['sku']) ?></td>
                                                <td class="ta-r pl-strong"><?= (int) $p['quantity'] ?></td>
                                                <td class="ta-r"><?= e(money($p['unit_price'])) ?></td>
                                                <td class="ta-r pl-total"><?= e(money($p['total_price'])) ?></td>
                                                <td class="ta-c">
                                                    <div class="pl-row-actions">
                                                        <?php if ($canEdit && $p['status'] === 'Pending Approval'): ?>
                                                        <button type="button" data-action="remove-part" aria-label="Remove <?= e($p['name']) ?>"><i class="fa-solid fa-trash"></i></button>
                                                        <?php else: ?>
                                                        <span style="color: #c5c5d4;" title="Only parts pending approval can be removed">&mdash;</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <!-- Labor hours -->
                            <section class="pl-card">
                                <div class="pl-card__head pl-card__head--plain">
                                    <h2 class="pl-card__title"><i class="fa-regular fa-clock"></i> Labor Recording</h2>
                                </div>

                                <?php if ($labor): ?>
                                <table class="pl-table" style="margin-bottom: 16px;">
                                    <tbody>
                                        <?php foreach ($labor as $l): ?>
                                        <tr data-labor-id="<?= (int) $l['id'] ?>">
                                            <td><?= e($l['description']) ?></td>
                                            <td class="ta-r pl-strong"><?= e(hours_label($l['hours'])) ?></td>
                                            <td class="ta-r pl-total"><?= e(money($l['total'])) ?></td>
                                            <td class="ta-c">
                                                <?php if ($canEdit): ?>
                                                <div class="pl-row-actions"><button type="button" data-action="remove-labor" aria-label="Remove labor entry"><i class="fa-solid fa-trash"></i></button></div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <?php endif; ?>

                                <?php if ($canEdit): ?>
                                <form class="pl-labor-grid" id="pl-labor-form" novalidate>
                                    <div class="pl-field">
                                        <label class="pl-label" for="start-time">Start Time</label>
                                        <div class="pl-input-icon">
                                            <i class="fa-regular fa-clock"></i>
                                            <input type="time" id="start-time" name="start_time" required>
                                        </div>
                                    </div>
                                    <div class="pl-field">
                                        <label class="pl-label" for="end-time">End Time</label>
                                        <div class="pl-input-icon">
                                            <i class="fa-regular fa-clock"></i>
                                            <input type="time" id="end-time" name="end_time" required>
                                        </div>
                                    </div>
                                    <div class="pl-field pl-field--full">
                                        <label class="pl-label" for="task-desc">Task Description</label>
                                        <textarea id="task-desc" name="description" class="pl-textarea" rows="3" maxlength="255" required placeholder="What was done, e.g. Replaced front brake pads and inspected rotors"></textarea>
                                    </div>
                                </form>

                                <div class="pl-card__foot">
                                    <button type="submit" form="pl-labor-form" class="pl-btn pl-btn--outline"><i class="fa-solid fa-circle-plus"></i> Record Labor</button>
                                </div>
                                <?php elseif (!$labor): ?>
                                <p style="color: #757684;">No labor recorded.</p>
                                <?php endif; ?>
                            </section>
                        </div>

                        <!-- Right column: summary -->
                        <aside class="pl-col-right">
                            <div class="pl-summary">
                                <h2 class="pl-summary__title">Summary</h2>

                                <dl class="pl-summary__rows">
                                    <div class="pl-summary__row">
                                        <dt>Total Parts Cost</dt>
                                        <dd><?= e(money($summary['parts_cost'])) ?></dd>
                                    </div>
                                    <div class="pl-summary__row">
                                        <dt>Total Labor Hours</dt>
                                        <dd><?= e(hours_label($summary['labor_hours'])) ?></dd>
                                    </div>
                                    <div class="pl-summary__row">
                                        <dt>Labor Rate (est)</dt>
                                        <dd><?= e(money($summary['labor_rate'])) ?>/hr</dd>
                                    </div>
                                </dl>

                                <div class="pl-total-box">
                                    <span class="pl-total-box__label">Est. Total</span>
                                    <span class="pl-total-box__value"><?= e(money($summary['estimated_total'])) ?></span>
                                </div>

                                <div class="pl-summary__actions">
                                    <!-- Entries are saved as they are added, so there is nothing separate to save -->
                                    <button type="button" class="pl-btn pl-btn--primary pl-btn--block" disabled title="Parts and labor are saved as you add them"><i class="fa-solid fa-floppy-disk"></i> Saved Automatically</button>
                                    <button type="button" class="pl-btn pl-btn--soft pl-btn--block" id="pl-notify"<?= $job['manager_id'] ? '' : ' disabled title="No manager assigned"' ?>><i class="fa-solid fa-paper-plane"></i> Notify Manager</button>
                                </div>
                            </div>
                        </aside>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-parts-labor.js"></script>
</body>

</html>
