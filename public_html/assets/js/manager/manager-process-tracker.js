/**
 * manager-process-tracker.js
 * Interactive Kanban Board with HTML5 Drag & Drop for AutoCare Job Tracking.
 * Columns: PENDING, IN PROGRESS, COMPLETED.
 * Now Fully Synced to MySQL Database!
 */

let jobCardsList = [];

document.addEventListener('DOMContentLoaded', () => {
  fetchJobCards();
  initKanbanDragAndDrop();

  setInterval(() => {
    fetchJobCards(true);
  }, 5000);
});

async function fetchJobCards(isBackground = false) {
  try {
    const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=list');
    const data = await res.json();
    if (data.success) {
      jobCardsList = data.cards || [];
      // Assign kanban stages dynamically based on DB status
      jobCardsList.forEach(j => {
        if (['Awaiting Parts', 'Diagnosis'].includes(j.status) || !j.status) {
          j.kanban_stage = 'PENDING';
        } else if (['Ready', 'Completed', 'Delivered'].includes(j.status)) {
          j.kanban_stage = 'COMPLETED';
        } else {
          j.kanban_stage = 'IN PROGRESS';
        }
      });
      renderKanbanBoard();
    }
  } catch (err) {
    if (!isBackground) {
      console.error('Failed to fetch job cards from DB.', err);
      if (typeof AutoCareStore !== 'undefined') {
        jobCardsList = AutoCareStore.getJobCards();
        renderKanbanBoard();
      }
    }
  }
}

/**
 * 1. Render Kanban Board Cards
 */
function renderKanbanBoard() {
  const columns = document.querySelectorAll('.kanban-board .kanban-column');
  if (columns.length < 3) return;

  const [pendingCol, inProgressCol, completedCol] = columns;

  const pendingBody = pendingCol.querySelector('.column-body');
  const inProgressBody = inProgressCol.querySelector('.column-body');
  const completedBody = completedCol.querySelector('.column-body');

  const pendingCards = jobCardsList.filter(j => j.kanban_stage === 'PENDING');
  const inProgressCards = jobCardsList.filter(j => j.kanban_stage === 'IN PROGRESS');
  const completedCards = jobCardsList.filter(j => j.kanban_stage === 'COMPLETED');

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

    const pct = c.progress_percentage || (stage === 'PENDING' ? 10 : (stage === 'COMPLETED' ? 100 : 50));

    const progressSection = stage === 'IN PROGRESS' ? `
      <div class="progress-section" style="margin: 12px 0;">
        <div class="progress-info" style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px;">
          <span>Progress</span>
          <span style="font-weight: 700;">${pct}%</span>
        </div>
        <div class="progress-bar" style="background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
          <div class="progress-fill" style="width: ${pct}%; background: #3b82f6; height: 100%;"></div>
        </div>
      </div>
    ` : '';

    const assigneeHtml = c.mechanic_id ? `
      <div class="assignee-avatar" style="display: flex; align-items: center; gap: 6px; font-size: 12px;">
        <span class="avatar-circle" style="background: #e2e8f0; font-weight: 700; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px;">${(c.mechanic_name || 'UN').substring(0,2).toUpperCase()}</span>
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

    col.addEventListener('drop', async (e) => {
      e.preventDefault();
      col.style.background = '';
      col.style.borderColor = '';

      const cardId = e.dataTransfer.getData('text/plain') || draggedCardId;
      if (!cardId) return;

      const card = jobCardsList.find(c => c.id == cardId);
      if (card && card.kanban_stage !== targetStage) {
        
        // Define default progress based on target column
        let newProgress = card.progress_percentage || 50;
        if (targetStage === 'PENDING') newProgress = 10;
        else if (targetStage === 'IN PROGRESS') newProgress = Math.max(20, Math.min(85, newProgress));
        else if (targetStage === 'COMPLETED') newProgress = 100;

        try {
          const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=update_kanban', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: cardId, kanban_stage: targetStage, progress_percentage: newProgress })
          });
          const data = await res.json();
          if (data.success) {
            showToast(`${card.code} moved to ${targetStage}!`, 'success');
            fetchJobCards(); // Refetch to sync board
          }
        } catch (err) {
            showToast(`${card.code} moved to ${targetStage} (Offline Mode)!`, 'warning');
            card.kanban_stage = targetStage;
            card.progress_percentage = newProgress;
            renderKanbanBoard();
        }
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
      const card = jobCardsList.find(c => c.id == cardId);
      if (!card) return;

      const pct = card.progress_percentage || (card.kanban_stage === 'PENDING' ? 10 : (card.kanban_stage === 'COMPLETED' ? 100 : 50));

      openModal({
        title: `Job Tracker: ${card.work_order || card.code}`,
        content: `
          <div style="font-size: 13.5px; line-height: 1.6;">
            <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 14px;">
              <h4 style="margin: 0 0 6px 0; font-size: 16px; color: #0f172a;">${card.vehicle_title || card.vehicle_details}</h4>
              <p style="margin: 0; color: #64748b; font-size: 12px;">VIN: ${card.vin || 'Pending'} | Priority: ${card.priority || 'Standard'}</p>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
              <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Customer</span>
                <div style="font-weight: 600;">${card.customer_name}</div>
              </div>
              <div>
                <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Assigned Mechanic</span>
                <div style="font-weight: 600;">${card.mechanic_name || 'Unassigned'}</div>
              </div>
            </div>

            <div style="margin-bottom: 14px;">
              <span style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase;">Service Scope</span>
              <p style="margin: 4px 0; color: #334155;">${card.description || card.service_text || 'Standard diagnosis and parts inspection.'}</p>
            </div>

            <div style="margin-bottom: 14px;">
              <div style="display: flex; justify-content: space-between; font-weight: 600; font-size: 13px; margin-bottom: 4px;">
                <span>Repair Completion</span>
                <span id="kanban-modal-progress-label">${pct}%</span>
              </div>
              <input type="range" id="kanban-modal-progress" min="0" max="100" value="${pct}" style="width: 100%; cursor: pointer;" oninput="document.getElementById('kanban-modal-progress-label').innerText = this.value + '%'">
            </div>

            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; padding-top: 12px; border-top: 1px solid #e2e8f0;">
              <a href="manager-jobCards.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">📋 Job Card Details</a>
              <a href="manager-chat.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">💬 Bay Chat</a>
              <a href="manager-cost-estimation.html" style="flex: 1; min-width: 120px; text-align: center; padding: 7px 10px; background: #f1f5f9; color: #1e293b; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; border: 1px solid #cbd5e1;">💰 Cost Estimate</a>
            </div>
          </div>
        `,
        confirmText: 'Save Progress',
        onConfirm: async () => {
          const newPct = parseInt(document.getElementById('kanban-modal-progress').value, 10);
          
          let targetStage = 'IN PROGRESS';
          if (newPct >= 100) targetStage = 'COMPLETED';
          else if (newPct <= 10) targetStage = 'PENDING';

          try {
            const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=update_kanban', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ id: cardId, kanban_stage: targetStage, progress_percentage: newPct })
            });
            const data = await res.json();
            if (data.success) {
              showToast(`Progress for ${card.code} updated to ${newPct}%!`, 'success');
              fetchJobCards(); // Refetch to sync board
            }
          } catch (err) {
            showToast(`Progress for ${card.code} updated (Offline Mode)!`, 'warning');
            card.kanban_stage = targetStage;
            card.progress_percentage = newPct;
            renderKanbanBoard();
          }
        }
      });
    });
  });
}
