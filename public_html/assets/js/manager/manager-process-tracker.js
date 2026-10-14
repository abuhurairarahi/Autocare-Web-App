/**
 * manager-process-tracker.js
 * Interactive Kanban Board with HTML5 Drag & Drop for AutoCare Job Tracking.
 * Columns: PENDING, IN PROGRESS, COMPLETED.
 */

document.addEventListener('DOMContentLoaded', () => {
  renderKanbanBoard();
  initKanbanDragAndDrop();
});

window.addEventListener('autocare:store:synced', () => {
  renderKanbanBoard();
});

/**
 * 1. Render Kanban Board Cards from AutoCareStore
 */
function renderKanbanBoard() {
  if (typeof AutoCareStore === 'undefined') return;

  const jobCards = AutoCareStore.getJobCards();

  const columns = document.querySelectorAll('.kanban-board .kanban-column');
  if (columns.length < 3) return;

  const [pendingCol, inProgressCol, completedCol] = columns;

  const pendingBody = pendingCol.querySelector('.column-body');
  const inProgressBody = inProgressCol.querySelector('.column-body');
  const completedBody = completedCol.querySelector('.column-body');

  const pendingCards = jobCards.filter(j => j.kanban_stage === 'PENDING');
  const inProgressCards = jobCards.filter(j => j.kanban_stage === 'IN PROGRESS');
  const completedCards = jobCards.filter(j => j.kanban_stage === 'COMPLETED');

  // Update Counters
  pendingCol.querySelector('.column-count').innerText = pendingCards.length;
  inProgressCol.querySelector('.column-count').innerText = inProgressCards.length;
  completedCol.querySelector('.column-count').innerText = completedCards.length;

  // Render Columns
  pendingBody.innerHTML = renderColumnCardsHtml(pendingCards, 'PENDING');
  inProgressBody.innerHTML = renderColumnCardsHtml(inProgressCards, 'IN PROGRESS');
  completedBody.innerHTML = renderColumnCardsHtml(completedCards, 'COMPLETED');

  // Attach card click handlers for details inspection
  attachCardClickHandlers();
}

function renderColumnCardsHtml(cards, stage) {
  if (cards.length === 0) {
    let emptyText = 'No pending jobs';
    if (stage === 'IN PROGRESS') emptyText = 'No jobs in progress';
    if (stage === 'COMPLETED') emptyText = 'No completed jobs yet';

    return `
      <div class="empty-state-container" style="display: flex; align-items: center; justify-content: center; height: 180px; width: 100%;">
        <div class="empty-state" style="text-align: center; color: #94a3b8;">
          <div class="empty-icon" style="margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" style="width: 32px; height: 32px; stroke: #94a3b8; fill: none; stroke-width: 1.5;">
              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
              <polyline points="22 4 12 14.01 9 11.01"></polyline>
            </svg>
          </div>
          <p style="font-size: 13px; margin: 0;">${emptyText}</p>
        </div>
      </div>
    `;
  }

  return cards.map(c => {
    const priorityBadge = c.priority === 'High' 
      ? '<span class="badge badge-high" style="background: #fee2e2; color: #ef4444; font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600;">High Priority</span>'
      : '<span class="badge badge-standard" style="background: #e0f2fe; color: #0284c7; font-size: 11px; padding: 2px 8px; border-radius: 999px; font-weight: 600;">Standard</span>';

    const progressSection = stage === 'IN PROGRESS' ? `
      <div class="progress-section" style="margin: 12px 0;">
        <div class="progress-info" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
          <span>Progress</span>
          <span style="font-weight: 700;">${c.progress_percentage || 50}%</span>
        </div>
        <div class="progress-bar" style="background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
          <div class="progress-fill" style="width: ${c.progress_percentage || 50}%; background: #3b82f6; height: 100%;"></div>
        </div>
      </div>
    ` : '';

    const assigneeHtml = c.mechanic_id ? `
      <div class="assignee-avatar" style="display: flex; align-items: center; gap: 6px; font-size: 12px;">
        <span class="avatar-circle" style="background: #e2e8f0; font-weight: 700; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px;">${c.mechanic_initials || 'ME'}</span>
        <span>${c.mechanic_name}</span>
      </div>
    ` : `
      <div class="assignee" style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: #94a3b8;">
        <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; stroke: currentColor; fill: none;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        <span>Unassigned</span>
      </div>
    `;

    return `
      <div class="job-card" draggable="true" data-card-id="${c.id}" style="cursor: grab; user-select: none; margin-bottom: 14px; transition: transform 0.2s, box-shadow 0.2s;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
          <span class="job-id" style="font-weight: 700; color: #2563eb;">${c.work_order || c.code}</span>
          ${priorityBadge}
        </div>

        <div class="card-body">
          <h3 class="vehicle-title" style="margin: 0 0 4px 0; font-size: 15px;">${c.vehicle_title || c.vehicle_details}</h3>
          <p class="vin-text" style="font-size: 12px; color: #64748b; margin: 0 0 10px 0;">VIN: ${c.vin || 'Pending'}</p>
          
          <div class="customer-info" style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; margin-bottom: 8px;">
            <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; stroke: currentColor; fill: none;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            <span>${c.customer_name}</span>
          </div>

          ${progressSection}
        </div>

        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9;">
          ${assigneeHtml}
          <div class="due-date" style="display: flex; align-items: center; gap: 4px; font-size: 12px; color: #64748b;">
            <svg viewBox="0 0 24 24" style="width: 13px; height: 13px; stroke: currentColor; fill: none;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            <span>${c.delivery_date || 'Due Today'}</span>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

/**
 * 2. HTML5 Drag and Drop Event Listeners
 */
function initKanbanDragAndDrop() {
  const board = document.querySelector('.kanban-board');
  if (!board) return;

  let draggedCardId = null;

  board.addEventListener('dragstart', (e) => {
    const card = e.target.closest('.job-card');
    if (!card) return;

    draggedCardId = card.getAttribute('data-card-id');
    e.dataTransfer.setData('text/plain', draggedCardId);
    e.dataTransfer.effectAllowed = 'move';

    card.style.opacity = '0.4';
    card.style.transform = 'scale(0.98)';
  });

  board.addEventListener('dragend', (e) => {
    const card = e.target.closest('.job-card');
    if (card) {
      card.style.opacity = '1';
      card.style.transform = 'none';
    }
    // Remove hover styles from all columns
    document.querySelectorAll('.kanban-column').forEach(col => {
      col.style.background = '';
      col.style.borderColor = '';
    });
  });

  const columns = document.querySelectorAll('.kanban-column');
  const stageMap = ['PENDING', 'IN PROGRESS', 'COMPLETED'];

  columns.forEach((col, index) => {
    const targetStage = stageMap[index];

    col.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      col.style.background = 'rgba(238, 242, 255, 0.6)';
      col.style.borderColor = '#6366f1';
    });

    col.addEventListener('dragleave', (e) => {
      if (!col.contains(e.relatedTarget)) {
        col.style.background = '';
        col.style.borderColor = '';
      }
    });

    col.addEventListener('drop', (e) => {
      e.preventDefault();
      col.style.background = '';
      col.style.borderColor = '';

      const cardId = e.dataTransfer.getData('text/plain') || draggedCardId;
      if (!cardId) return;

      const card = AutoCareStore.getJobCardById(cardId);
      if (card && card.kanban_stage !== targetStage) {
        let newStatus = 'Repairing';
        if (targetStage === 'PENDING') newStatus = 'Diagnosis';
        else if (targetStage === 'COMPLETED') newStatus = 'Ready';

        AutoCareStore.updateJobCardStatus(card.id, newStatus, targetStage);
        showToast(`${card.work_order || card.code} moved to ${targetStage}!`, 'success');
        renderKanbanBoard();
      }
    });
  });
}

/**
 * 3. Inspect Job Card Details on click
 */
function attachCardClickHandlers() {
  document.querySelectorAll('.kanban-board .job-card').forEach(cardEl => {
    cardEl.addEventListener('click', () => {
      const cardId = cardEl.getAttribute('data-card-id');
      const card = AutoCareStore.getJobCardById(cardId);
      if (!card) return;

      openModal({
        title: `Job Tracker: ${card.work_order || card.code}`,
        content: `
          <div style="font-size: 13.5px; line-height: 1.6;">
            <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 14px;">
              <h4 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a;">${card.vehicle_title}</h4>
              <p style="margin: 0; color: #64748b; font-size: 12px;">VIN: ${card.vin} | License: ${card.vehicle_details}</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Customer</span>
                <div style="font-weight: 600;">${card.customer_name}</div>
              </div>
              <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Assigned Mechanic</span>
                <div style="font-weight: 600;">${card.mechanic_name}</div>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Service Scope</span>
              <p style="margin: 4px 0; color: #334155;">${card.service_text || 'Standard diagnosis and parts inspection.'}</p>
            </div>

            <div style="margin-bottom: 14px;">
              <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
                <span>Repair Completion</span>
                <span>${card.progress_percentage}%</span>
              </div>
              <input type="range" id="kanban-modal-progress" min="0" max="100" value="${card.progress_percentage}" style="width: 100%;">
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
              <a href="manager-jobCards.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">📋 Job Card Details</a>
              <a href="manager-chat.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">💬 Bay Chat</a>
              <a href="manager-cost-estimation.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">💰 Cost Estimate</a>
            </div>
          </div>
        `,
        confirmText: 'Save Progress',
        onConfirm: () => {
          const newPct = parseInt(document.getElementById('kanban-modal-progress').value, 10);
          card.progress_percentage = newPct;
          if (newPct >= 100) {
            AutoCareStore.updateJobCardStatus(card.id, 'Ready', 'COMPLETED');
          } else if (newPct <= 20) {
            AutoCareStore.updateJobCardStatus(card.id, 'Diagnosis', 'PENDING');
          } else {
            AutoCareStore.updateJobCardStatus(card.id, 'Repairing', 'IN PROGRESS');
          }
          showToast(`Progress for ${card.code} updated to ${newPct}%!`, 'success');
          renderKanbanBoard();
        }
      });
    });
  });
}
