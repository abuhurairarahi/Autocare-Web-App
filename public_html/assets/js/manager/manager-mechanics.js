/**
 * manager-mechanics.js
 * Mechanics management logic for AutoCare Manager Panel:
 * Specialization & Availability filtering, live workload bars,
 * Job Card assignment modals, and mechanic profile inspection.
 */

let specFilter = 'all';
let availFilter = 'all';

document.addEventListener('DOMContentLoaded', () => {
  renderMechanicCards();
  initMechanicFilters();
});

window.addEventListener('autocare:store:synced', () => {
  renderMechanicCards();
});

/**
 * 1. Render Mechanic Cards from AutoCareStore
 */
function renderMechanicCards() {
  const grid = document.querySelector('.mechanic-grid');
  if (!grid || typeof AutoCareStore === 'undefined') return;

  const mechanics = AutoCareStore.getMechanics();
  const jobCards = AutoCareStore.getJobCards();

  let filtered = mechanics;

  // Filter Specialization
  if (specFilter !== 'all') {
    filtered = filtered.filter(m => m.specialty.toLowerCase().includes(specFilter.toLowerCase()));
  }

  // Filter Availability
  if (availFilter !== 'all') {
    filtered = filtered.filter(m => m.status.toLowerCase() === availFilter.toLowerCase());
  }

  if (filtered.length === 0) {
    grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 40px; color: #94a3b8; font-size: 14px;">No mechanics match the selected filters.</div>`;
    return;
  }

  let html = '';
  filtered.forEach(m => {
    // Count active jobs
    const activeJobs = jobCards.filter(j => j.mechanic_id === m.id && j.kanban_stage !== 'COMPLETED').length;
    const workloadPct = m.workload || Math.min(100, activeJobs * 25);
    const isBusy = m.status === 'Busy' || workloadPct >= 80;
    const isOffShift = m.status === 'Off Shift';

    let badgeClass = 'available';
    let statusText = 'AVAILABLE';
    let btnHtml = `<button class="btn btn-primary" onclick="openAssignJobModal(${m.id})">Assign Job</button>`;

    if (isOffShift) {
      badgeClass = 'off-shift';
      statusText = 'OFF SHIFT';
      btnHtml = `<button class="btn btn-disabled" disabled style="opacity: 0.5; cursor: not-allowed;">Off Shift</button>`;
    } else if (isBusy) {
      badgeClass = 'busy';
      statusText = 'BUSY';
      btnHtml = `<button class="btn btn-primary" onclick="openAssignJobModal(${m.id})" style="background: #475569;">Reassign / Add</button>`;
    }

    const progressFillClass = workloadPct >= 80 ? 'high' : 'low';

    html += `
      <article class="mechanic-card" data-mechanic-id="${m.id}">
        <div class="card-header">
          <img src="${m.avatar || 'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=150'}" alt="${m.name}" class="mechanic-avatar">
          <div class="mechanic-info">
            <div class="name-status">
              <h3>${m.name}</h3>
              <span class="status-badge ${badgeClass}">${statusText}</span>
            </div>
            <p class="specialty">${m.specialty}</p>
          </div>
        </div>

        <div class="card-body">
          <div class="info-row">
            <span class="label">Experience</span>
            <span class="value">${m.experience || '5 Years'}</span>
          </div>

          <div class="workload-section">
            <div class="workload-header">
              <span class="label">CURRENT WORKLOAD</span>
              <span class="percentage ${progressFillClass}">${workloadPct}%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill ${progressFillClass}" style="width: ${workloadPct}%;"></div>
            </div>
          </div>

          <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;">
            ${btnHtml}
            <button class="btn" onclick="window.location.href='manager-chat.html'" style="background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 12.5px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px;">
              <svg viewBox="0 0 24 24" style="width: 14px; height: 14px; fill: none; stroke: currentColor; stroke-width: 2;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
              Chat with ${m.name.split(' ')[0]}
            </button>
          </div>
        </div>
      </article>
    `;
  });

  grid.innerHTML = html;
}

/**
 * 2. Filter Dropdowns
 */
function initMechanicFilters() {
  const selects = document.querySelectorAll('.page-heading .filters select');
  if (selects.length < 2) return;

  const [specSelect, availSelect] = selects;

  specSelect.addEventListener('change', (e) => {
    const val = e.target.value.toLowerCase();
    specFilter = val.includes('all') ? 'all' : val;
    renderMechanicCards();
    showToast(`Filtered by: ${e.target.value}`, 'info');
  });

  availSelect.addEventListener('change', (e) => {
    const val = e.target.value.toLowerCase();
    if (val.includes('any')) availFilter = 'all';
    else if (val.includes('available')) availFilter = 'available';
    else if (val.includes('busy')) availFilter = 'busy';
    else if (val.includes('off')) availFilter = 'off shift';
    renderMechanicCards();
    showToast(`Availability: ${e.target.value}`, 'info');
  });
}

/**
 * 3. Assign Job Modal
 */
window.openAssignJobModal = function (mechanicId) {
  const mech = AutoCareStore.getMechanicById(mechanicId);
  if (!mech) return;

  const jobCards = AutoCareStore.getJobCards().filter(j => j.kanban_stage !== 'COMPLETED');
  const cardOptions = jobCards.map(c => `
    <option value="${c.id}">${c.code} - ${c.customer_name} (${c.vehicle_title || c.vehicle_details})</option>
  `).join('');

  openModal({
    title: `Assign Job to ${mech.name}`,
    content: `
      <div style="font-size: 13.5px; line-height: 1.5;">
        <p>Assign an active job card to <strong>${mech.name}</strong> (${mech.specialty}).</p>
        <div style="margin-top: 14px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Select Job Card</label>
          <select id="modal-assign-job-select" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            ${cardOptions}
          </select>
        </div>
        <div style="margin-top: 14px;">
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Work Bay / Notes</label>
          <input type="text" id="modal-assign-notes" placeholder="e.g. Bay 3 - Lift required" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
        </div>
      </div>
    `,
    confirmText: 'Assign Now',
    onConfirm: () => {
      const jobId = document.getElementById('modal-assign-job-select').value;
      if (jobId) {
        AutoCareStore.assignJobCardMechanic(jobId, mech.id);
        showToast(`Job assigned to ${mech.name}!`, 'success');
        renderMechanicCards();
        setTimeout(() => {
          openModal({
            title: 'Job Assigned Successfully',
            content: `<p>Job has been assigned to <strong>${mech.name}</strong>.</p>`,
            confirmText: 'View in Process Tracker',
            cancelText: 'Stay on Mechanics',
            onConfirm: () => {
              window.location.href = 'manager-process-tracker.html';
            }
          });
        }, 300);
      }
    }
  });
};
