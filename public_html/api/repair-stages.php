<?php
/**
 * repair-stages.php
 * Maps JobCards.status onto the customer-facing repair steps, and onto the
 * kanban_stage / progress_percentage columns the Manager panel reads.
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

/** Statuses a mechanic may set, with the kanban stage and progress each implies. */
const MECHANIC_STATUS_FLOW = [
    'Diagnosis' => ['kanban' => 'PENDING', 'progress' => 25],
    'Awaiting Parts' => ['kanban' => 'IN PROGRESS', 'progress' => 40],
    'Repairing' => ['kanban' => 'IN PROGRESS', 'progress' => 65],
    'Testing' => ['kanban' => 'IN PROGRESS', 'progress' => 90],
    'Completed' => ['kanban' => 'COMPLETED', 'progress' => 100],
];
