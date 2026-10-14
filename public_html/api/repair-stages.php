<?php
/**
 * repair-stages.php
 * Maps JobCards.status onto the customer-facing repair steps, and derives the
 * kanban stage / progress percentage shown in the Mechanic panel (the schema
 * stores neither).
 */

const REPAIR_STEPS = ['Received', 'Diagnosis', 'Repairing', 'Testing', 'Ready'];

/** Index into REPAIR_STEPS for a JobCards.status value. */
function repair_step_index(?string $status): int
{
    switch ($status) {
        case 'Diagnosis':
            return 1;
        case 'In Progress':
        case 'Repairing':
        case 'Awaiting Parts':
            return 2;
        case 'Testing':
            return 3;
        case 'Ready':
        case 'Completed':
        case 'Delivered':
            return 4;
        default: // 'Assigned' or unknown
            return 0;
    }
}

/**
 * Statuses a mechanic may set: the JobCards.status value stored for each (the enum has
 * no 'Awaiting Parts' / 'Repairing', so both are stored as 'In Progress' and the exact
 * stage is kept in RepairTimeline.stage), plus the kanban stage and progress it implies.
 */
const MECHANIC_STATUS_FLOW = [
    'Diagnosis' => ['db' => 'Diagnosis', 'kanban' => 'PENDING', 'progress' => 25],
    'Awaiting Parts' => ['db' => 'In Progress', 'kanban' => 'IN PROGRESS', 'progress' => 40],
    'Repairing' => ['db' => 'In Progress', 'kanban' => 'IN PROGRESS', 'progress' => 65],
    'Testing' => ['db' => 'Testing', 'kanban' => 'IN PROGRESS', 'progress' => 90],
    'Completed' => ['db' => 'Completed', 'kanban' => 'COMPLETED', 'progress' => 100],
];

/** Sub-stages of JobCards.status 'In Progress' that are recorded in RepairTimeline.stage. */
const IN_PROGRESS_STAGES = ['Awaiting Parts', 'Repairing'];

/**
 * SQL expression for the displayed status of job card alias `j`: JobCards.status, or the
 * latest timeline sub-stage while the card is 'In Progress'.
 */
function display_status_sql(string $j = 'j'): string
{
    return "IF({$j}.status = 'In Progress',
               COALESCE((SELECT t.stage FROM RepairTimeline t
                          WHERE t.job_card_id = {$j}.id
                          ORDER BY t.updated_at DESC, t.id DESC LIMIT 1), {$j}.status),
               {$j}.status)";
}

/** Normalise a display_status_sql() result: unknown 'In Progress' sub-stages fall back to 'In Progress'. */
function display_status(string $dbStatus, ?string $shown): string
{
    if ($dbStatus === 'In Progress') {
        return in_array($shown, IN_PROGRESS_STAGES, true) ? $shown : 'In Progress';
    }
    return $dbStatus;
}

/** Kanban stage and progress percentage implied by a displayed status. */
function status_progress(string $status): array
{
    if (isset(MECHANIC_STATUS_FLOW[$status])) {
        return ['kanban' => MECHANIC_STATUS_FLOW[$status]['kanban'], 'progress' => MECHANIC_STATUS_FLOW[$status]['progress']];
    }
    switch ($status) {
        case 'In Progress':
            return ['kanban' => 'IN PROGRESS', 'progress' => 50];
        case 'Ready':
            return ['kanban' => 'COMPLETED', 'progress' => 95];
        case 'Delivered':
            return ['kanban' => 'COMPLETED', 'progress' => 100];
        default: // 'Assigned'
            return ['kanban' => 'PENDING', 'progress' => 0];
    }
}

/** Display codes, built the same way as the team's manager APIs. */
function job_code(int $id, ?string $createdAt): string
{
    return 'JC-' . date('Y', strtotime($createdAt ?: 'now')) . '-' . $id;
}

function appointment_code(int $id, ?string $createdAt): string
{
    return 'BRQ-' . date('Y', strtotime($createdAt ?: 'now')) . '-' . $id;
}

function invoice_number(int $id, ?string $issuedDate): string
{
    return 'INV-' . date('Y', strtotime($issuedDate ?: 'now')) . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function estimate_code(int $id, ?string $createdAt): string
{
    return 'EST-' . date('Y', strtotime($createdAt ?: 'now')) . '-' . $id;
}
