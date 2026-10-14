<?php
require_once __DIR__ . '/../../api/auth.php';
require_once __DIR__ . '/../../api/mechanic/mechanic-data.php';

$user = require_page_role('Mechanic');
$mechanicId = $user['id'];

$openJobs = mechanic_jobs($pdo, $mechanicId, ['status' => 'open']);
$requestedId = input_id($_GET['job_id'] ?? null) ?: ($openJobs[0]['id'] ?? null);
$job = $requestedId ? mechanic_job($pdo, $mechanicId, $requestedId) : null;
$reports = $job ? job_fault_reports($pdo, $job) : [];
$canReport = $job && $job['is_open'];

function severity_badge(?string $severity): array
{
    return [
        'High' => ['fr-severity--high', 'fa-circle-exclamation', 'HIGH'],
        'Medium' => ['fr-severity--med', 'fa-triangle-exclamation', 'MED'],
        'Low' => ['fr-severity--low', 'fa-circle-info', 'LOW'],
    ][$severity] ?? ['fr-severity--low', 'fa-note-sticky', 'NOTE'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Mechanic - Report Faults</title>
    <link rel="stylesheet" href="../../assets/css/mechanic/mechanic-fault-report.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/shared/default-sidebar-topbar-style.css">
    <style>.fr-report-card[hidden] { display: none; }</style>
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
                <a href="mechanic-fault-report.php" class="nav-item active"><i class="fa-solid fa-triangle-exclamation"></i> Report Faults</a>
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
                <div class="fr-wrap">
                    <div class="fr-grid">

                        <!-- Left column: report form -->
                        <div class="fr-col-left">
                            <div class="fr-header">
                                <h1>New Fault Report</h1>
                                <?php if ($job): ?>
                                <p>Log an unexpected issue found during inspection for Job #<?= e($job['code']) ?><?= $job['vehicle'] ? ' (' . e($job['vehicle']) . ')' : '' ?>.</p>
                                <?php else: ?>
                                <p><?= isset($_GET['job_id']) ? 'This job does not exist or is not assigned to you.' : 'You have no open jobs to report on.' ?> <a href="mechanic-assigned-jobs.php">Back to Assigned Jobs</a></p>
                                <?php endif; ?>
                                <?php if (count($openJobs) > 1 || ($job && !$job['is_open'])): ?>
                                <select id="fr-job-switch" aria-label="Choose job" style="margin-top: 8px; padding: 8px 10px; border-radius: 6px; border: 1px solid #c5c5d4; font: inherit;">
                                    <?php if ($job && !$job['is_open']): ?>
                                    <option value="<?= (int) $job['id'] ?>" selected><?= e($job['code']) ?> (<?= e($job['status']) ?>)</option>
                                    <?php endif; ?>
                                    <?php foreach ($openJobs as $oj): ?>
                                    <option value="<?= (int) $oj['id'] ?>"<?= $job && $oj['id'] === $job['id'] ? ' selected' : '' ?>><?= e($oj['code'] . ' · ' . ($oj['vehicle'] ?: $oj['service_text'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php endif; ?>
                            </div>

                            <?php if ($canReport): ?>
                            <section class="fr-form-card">
                                <form class="fr-form" id="fr-form" data-job-id="<?= (int) $job['id'] ?>" novalidate>
                                    <div class="fr-field fr-field--full">
                                        <label class="fr-label" for="fault-title">Fault Title *</label>
                                        <input type="text" id="fault-title" name="title" maxlength="120" required placeholder="Brief description of the issue">
                                    </div>

                                    <div class="fr-field">
                                        <label class="fr-label" for="fault-category">Category *</label>
                                        <div class="fr-select-wrap">
                                            <select id="fault-category" name="category" required>
                                                <option value="" selected disabled>Select Category</option>
                                                <?php foreach (FAULT_CATEGORIES as $cat): ?>
                                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </div>

                                    <div class="fr-field">
                                        <label class="fr-label" for="fault-severity">Severity *</label>
                                        <div class="fr-select-wrap">
                                            <select id="fault-severity" name="severity" required>
                                                <option value="" selected disabled>Select Severity</option>
                                                <?php foreach (FAULT_SEVERITIES as $sev): ?>
                                                <option value="<?= e($sev) ?>"><?= e($sev) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </div>

                                    <div class="fr-field fr-field--full">
                                        <label class="fr-label" for="fault-description">Detailed Description</label>
                                        <textarea id="fault-description" name="description" maxlength="2000" rows="4" placeholder="Describe the fault in detail, including diagnostic steps taken..."></textarea>
                                    </div>

                                    <div class="fr-field">
                                        <label class="fr-label" for="fault-cost">Estimated Cost (৳)</label>
                                        <input type="number" id="fault-cost" name="estimated_cost" min="0" max="10000000" step="0.01" placeholder="0.00">
                                    </div>

                                    <div class="fr-field fr-field--full">
                                        <label class="fr-label" for="fault-recommendation">Mechanic Recommendation</label>
                                        <input type="text" id="fault-recommendation" name="recommendation" maxlength="255" placeholder="E.g., Replace immediately, monitor for next service">
                                    </div>

                                    <div class="fr-field fr-field--full">
                                        <label class="fr-label" for="fault-photos">Photographic Evidence</label>
                                        <label class="fr-upload" for="fault-photos">
                                            <i class="fa-solid fa-camera"></i>
                                            <p><span class="fr-upload__link">Upload a file</span> or drag and drop</p>
                                            <span class="fr-upload__hint" id="fr-photo-name">JPEG, PNG or WebP up to 5MB (added to the job's repair photos)</span>
                                            <input type="file" id="fault-photos" accept="image/jpeg,image/png,image/webp" hidden>
                                        </label>
                                    </div>

                                    <div class="fr-actions">
                                        <button type="button" class="fr-btn fr-btn--outline">Cancel</button>
                                        <button type="submit" class="fr-btn fr-btn--primary"><i class="fa-solid fa-paper-plane"></i> Submit Report</button>
                                    </div>
                                </form>
                            </section>
                            <?php endif; ?>
                        </div>

                        <!-- Right column: recent reports -->
                        <aside class="fr-col-right">
                            <div class="fr-side__head">
                                <h2>Recent Job Reports</h2>
                                <?php if ($job): ?>
                                <span class="fr-tag"><?= e($job['code']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="fr-reports">
                                <?php if (empty($reports)): ?>
                                <p style="color: #757684;">No faults reported on this job yet.</p>
                                <?php endif; ?>
                                <?php foreach ($reports as $i => $r):
                                    [$sevClass, $sevIcon, $sevLabel] = severity_badge($r['severity']);
                                ?>
                                <article class="fr-report-card<?= $r['severity'] === null || $r['severity'] === 'Low' ? ' fr-report-card--muted' : '' ?>"<?= $i >= 3 ? ' hidden data-extra' : '' ?>>
                                    <div class="fr-report-card__top">
                                        <div class="fr-report-card__heading">
                                            <p class="fr-report-card__cat"><?= e($r['category']) ?></p>
                                            <h3 class="fr-report-card__title"><?= e($r['title']) ?></h3>
                                        </div>
                                        <span class="fr-severity <?= $sevClass ?>"><i class="fa-solid <?= $sevIcon ?>"></i> <?= $sevLabel ?></span>
                                    </div>
                                    <p class="fr-report-card__desc" style="white-space: pre-line;"><?= e($r['body']) ?></p>
                                    <?php if ($r['reported']): ?>
                                    <div class="fr-report-card__foot">
                                        <span class="fr-report-card__meta"><i class="fa-regular fa-clock"></i> <?= e(date('M d, Y g:i A', strtotime($r['reported']['at']))) ?><?= $r['reported']['by'] ? ' by ' . e($r['reported']['by']) : '' ?></span>
                                    </div>
                                    <?php endif; ?>
                                </article>
                                <?php endforeach; ?>

                                <?php if (count($reports) > 3): ?>
                                <button type="button" class="fr-view-all" id="fr-view-all">View All Reports (<?= count($reports) ?>) <i class="fa-solid fa-arrow-right"></i></button>
                                <?php endif; ?>
                            </div>
                        </aside>
                    </div>
                </div>
            </section>
        </main>

    </div>
    <script src="../../assets/js/shared/autocare-core.js"></script>
    <script src="../../assets/js/mechanic/mechanic-common.js"></script>
    <script src="../../assets/js/mechanic/mechanic-fault-report.js"></script>
</body>

</html>
