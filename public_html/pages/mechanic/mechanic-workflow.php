<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$requestedId = input_id($_GET['job_id'] ?? null);
if (!$requestedId) {
    $openJobs = mechanic_jobs($pdo, $mechanicId, ['status' => 'open']);
    $requestedId = $openJobs[0]['id'] ?? null;
}
$job = $requestedId ? mechanic_job_detail($pdo, $mechanicId, $requestedId) : null;

$stageNames = [1 => 'Diagnosis', 2 => 'Repairing', 3 => 'Testing'];
$step = $job ? repair_step_index($job['status']) : 0;

// Who moved the job into each stage and when (timeline is newest first)
$stageEntry = [];
if ($job) {
    foreach (array_reverse($job['timeline']) as $entry) {
        $stageEntry[repair_step_index($entry['stage'])] = $entry;
    }
}

function elapsed(string $from, ?string $to): string
{
    $seconds = max(0, ($to ? strtotime($to) : now_ts()) - strtotime($from));
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $days > 0 ? "{$days}d {$hours}h" : "{$hours}h {$minutes}m";
}

function time_ago(string $when): string
{
    $s = max(0, now_ts() - strtotime($when));
    if ($s < 3600) return max(1, intdiv($s, 60)) . ' mins ago';
    if ($s < 86400) return intdiv($s, 3600) . ' hours ago';
    return date('M d, g:i A', strtotime($when));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Progress</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-workflow.css">
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
                <a href="mechanic-workflow.php" class="nav-item active"><i class="fa-solid fa-diagram-project"></i> Progress</a>
                <a href="mechanic-parts-labor.php" class="nav-item"><i class="fa-solid fa-toolbox"></i> Parts &amp; Labor</a>
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
                <div class="wf-wrap">
                    <?php if (!$job): ?>
                    <div class="wf-header">
                        <div class="wf-header__info">
                            <h1 class="wf-title"><?= isset($_GET['job_id']) ? 'Job not found' : 'No active jobs' ?></h1>
                            <p class="wf-client"><?= isset($_GET['job_id']) ? 'This job does not exist or is not assigned to you.' : 'You have no open jobs right now.' ?> <a href="mechanic-assigned-jobs.php">Back to Assigned Jobs</a></p>
                        </div>
                    </div>
                    <?php else: ?>

                    <!-- Job detail header -->
                    <div class="wf-header" data-job-id="<?= (int) $job['id'] ?>" data-job-status="<?= e($job['status']) ?>" data-job-open="<?= $job['is_open'] ? '1' : '0' ?>">
                        <div class="wf-header__info">
                            <div class="wf-eyebrow">
                                <span class="wf-eyebrow__id">Job #<?= e($job['code']) ?></span>
                                <span class="wf-pill-priority"><?= e($job['priority']) ?> Priority</span>
                            </div>
                            <h1 class="wf-title"><?= e($job['vehicle'] ?: $job['service_text']) ?></h1>
                            <p class="wf-client"><i class="fa-solid fa-user"></i> Client: <?= e($job['owner_name'] ?: 'Walk-in') ?></p>
                        </div>
                        <div class="wf-header__actions">
                            <button class="wf-btn wf-btn--muted" id="wf-change-status"<?= $job['is_open'] ? '' : ' disabled' ?>><i class="fa-solid fa-ellipsis"></i> Change Status</button>
                            <button class="wf-btn wf-btn--primary" id="wf-complete"<?= $job['is_open'] ? '' : ' disabled' ?>><i class="fa-solid fa-circle-check"></i> <?= $job['is_open'] ? 'Complete Task' : 'Completed' ?></button>
                        </div>
                    </div>

                    <!-- Two column layout -->
                    <div class="wf-grid">

                        <!-- Left column -->
                        <div class="wf-col-left">

                            <!-- Overall status card -->
                            <article class="wf-status">
                                <div class="wf-status__main">
                                    <span class="wf-status__icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                                    <div>
                                        <h2 class="wf-status__title"><?= e(ucwords(strtolower($job['kanban_stage'] ?: 'Pending'))) ?> - <?= e($job['status']) ?></h2>
                                        <p class="wf-status__sub">
                                            <?php if ($job['is_open']): ?>
                                            Estimated completion: <?= e($job['delivery_date'] ?: 'Not set') ?>
                                            <?php else: ?>
                                            Completed: <?= e($job['completion_date'] ? date('M d, Y g:i A', strtotime($job['completion_date'])) : '—') ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                                <div class="wf-status__kpis">
                                    <div class="wf-kpi">
                                        <span class="wf-kpi__label">Time Elapsed</span>
                                        <span class="wf-kpi__value"><?= e($job['start_date'] ? elapsed($job['start_date'], $job['completion_date']) : '—') ?></span>
                                    </div>
                                    <div class="wf-kpi">
                                        <span class="wf-kpi__label">Stage Progress</span>
                                        <span class="wf-kpi__value"><?= (int) $job['progress_percentage'] ?>%</span>
                                    </div>
                                </div>
                            </article>

                            <!-- Workflow stages -->
                            <article class="wf-stages-card">
                                <h3 class="wf-stages-card__title">Workflow Stages</h3>
                                <ol class="wf-stages">
                                    <span class="wf-stages__line" aria-hidden="true"></span>

                                    <?php foreach ($stageNames as $idx => $name):
                                        $state = $idx < $step ? 'done' : ($idx === max(1, $step) ? 'active' : 'upcoming');
                                        $entry = $stageEntry[$idx] ?? null;
                                        $icons = [1 => 'fa-magnifying-glass', 2 => 'fa-gear', 3 => 'fa-gauge-high'];
                                    ?>
                                    <li class="wf-stage wf-stage--<?= $state ?>">
                                        <span class="wf-stage__marker"><i class="fa-solid <?= $state === 'done' ? 'fa-check' : $icons[$idx] ?>"></i></span>
                                        <div class="wf-stage__body<?= $state === 'active' ? ' wf-stage__card' : '' ?>">
                                            <div class="wf-stage__row">
                                                <h4 class="wf-stage__name<?= $state === 'active' ? ' wf-stage__name--active' : '' ?>">Stage <?= $idx ?>: <?= e($name) ?></h4>
                                                <?php if ($state === 'active'): ?>
                                                <span class="wf-pill-active"><?= $job['status'] === 'Awaiting Parts' ? 'Awaiting Parts' : 'Active' ?></span>
                                                <?php elseif ($state === 'upcoming'): ?>
                                                <span class="wf-pill-pending">Pending</span>
                                                <?php elseif ($entry): ?>
                                                <span class="wf-stage__time"><?= e(date('M d, g:i A', strtotime($entry['updated_at']))) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($state === 'done' && $entry): ?>
                                            <p class="wf-stage__meta">Started by <?= e($entry['updated_by_name'] ?: 'workshop') ?></p>
                                            <?php elseif ($idx === 1): ?>
                                            <p class="wf-stage__meta"><?= e($job['fault_report'] ?: ($job['issue_description'] ?: 'Inspect the vehicle and record findings.')) ?></p>
                                            <?php elseif ($idx === 3 && $state !== 'active'): ?>
                                            <p class="wf-stage__meta">System pressure test &amp; road test.</p>
                                            <?php endif; ?>

                                            <?php if ($idx === 1): ?>
                                            <div class="wf-stage__cta">
                                                <button class="wf-btn wf-btn--outline" id="wf-send-estimate"<?= $job['manager_id'] ? '' : ' disabled title="No manager assigned"' ?>><i class="fa-solid fa-paper-plane"></i> Send Estimation</button>
                                            </div>
                                            <?php endif; ?>

                                            <?php if ($idx === 2 && $state === 'active'): ?>
                                            <!-- Static: the schema has no per-job task checklist -->
                                            <ul class="wf-tasks">
                                                <li class="wf-task">
                                                    <input class="wf-check" type="checkbox" id="task-1" checked>
                                                    <label class="wf-task__label wf-task__label--done" for="task-1">Drain coolant system completely</label>
                                                </li>
                                                <li class="wf-task">
                                                    <input class="wf-check" type="checkbox" id="task-2">
                                                    <label class="wf-task__label" for="task-2">
                                                        <span class="wf-task__title">Replace thermostat assembly</span>
                                                        <span class="wf-task__note">Torque bolts to spec (15 Nm). Ensure gasket is seated correctly.</span>
                                                    </label>
                                                </li>
                                                <li class="wf-task">
                                                    <input class="wf-check" type="checkbox" id="task-3">
                                                    <label class="wf-task__label" for="task-3">Flush and refill coolant</label>
                                                </li>
                                            </ul>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ol>
                            </article>
                        </div>

                        <!-- Right column -->
                        <aside class="wf-col-right">

                            <!-- Resources -->
                            <article class="wf-resources">
                                <h3 class="wf-card-eyebrow">Resources</h3>
                                <div class="wf-resources__tiles">
                                    <div class="wf-tile">
                                        <span class="wf-tile__icon"><i class="fa-solid fa-box-archive"></i></span>
                                        <span class="wf-tile__value"><?= count($job['parts']) ?></span>
                                        <span class="wf-tile__label">Parts Logged</span>
                                    </div>
                                    <div class="wf-tile">
                                        <span class="wf-tile__icon"><i class="fa-regular fa-clock"></i></span>
                                        <span class="wf-tile__value"><?= e(rtrim(rtrim(number_format(array_sum(array_column($job['labor'], 'hours')), 2), '0'), '.')) ?>h</span>
                                        <span class="wf-tile__label">Labor Logged</span>
                                    </div>
                                </div>
                                <a class="wf-link-btn" href="mechanic-parts-labor.php?job_id=<?= (int) $job['id'] ?>" style="text-decoration: none;">View Details <i class="fa-solid fa-arrow-right"></i></a>
                            </article>

                            <!-- Activity log -->
                            <article class="wf-activity">
                                <div class="wf-activity__head">
                                    <h3 class="wf-card-eyebrow">Activity Log</h3>
                                    <i class="fa-solid fa-ellipsis"></i>
                                </div>

                                <ul class="wf-log">
                                    <?php if (empty($job['timeline'])): ?>
                                    <li class="wf-log__item"><div class="wf-log__body"><p class="wf-log__text">No activity recorded yet.</p></div></li>
                                    <?php endif; ?>
                                    <?php foreach (array_slice($job['timeline'], 0, 8) as $entry): ?>
                                    <li class="wf-log__item">
                                        <?php if ($entry['updated_by_name']): ?>
                                        <span class="wf-avatar"><?= e(initials($entry['updated_by_name'])) ?></span>
                                        <?php else: ?>
                                        <span class="wf-avatar wf-avatar--icon"><i class="fa-solid fa-arrows-rotate"></i></span>
                                        <?php endif; ?>
                                        <div class="wf-log__body">
                                            <p class="wf-log__text"><?php if ($entry['updated_by_name']): ?><strong><?= e($entry['updated_by_name']) ?></strong> changed stage to<?php else: ?>Stage changed to<?php endif; ?> <strong><?= e($entry['stage']) ?></strong></p>
                                            <span class="wf-log__time"><?= e(time_ago($entry['updated_at'])) ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- Static: there is no notes table to store quick notes in -->
                                <div class="wf-add-note">
                                    <input type="text" class="wf-add-note__input" placeholder="Quick notes are not available yet" disabled>
                                    <button type="button" class="wf-add-note__send" aria-label="Send note" disabled><i class="fa-solid fa-paper-plane"></i></button>
                                </div>
                            </article>
                        </aside>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-workflow.js"></script>
</body>

</html>
