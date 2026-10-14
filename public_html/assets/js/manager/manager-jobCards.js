/**
 * manager-jobCards.js
 * Comprehensive logic for the Job Cards management page:
 * Stat counters, status filtering, creating job cards, row action menus,
 * stage progression, mechanic reassignment, invoice generation, and pagination.
 */

let currentStatusFilter = 'all';
let currentPage = 1;
const itemsPerPage = 5;

document.addEventListener('DOMContentLoaded', () => {
  renderJobCardsStats();
  renderJobCardsTable();
  initStatusFilterDropdown();
  initCreateJobCardButton();
  initPagination();
});

window.addEventListener('autocare:store:synced', () => {
  renderJobCardsStats();
  renderJobCardsTable();
});

/**
 * 1. Render Stat Cards
 */
function renderJobCardsStats() {
  if (typeof AutoCareStore === 'undefined') return;

  const jobCards = AutoCareStore.getJobCards();
  const totalActive = jobCards.filter(j => j.status !== 'Delivered' && j.kanban_stage !== 'COMPLETED').length;
  const awaitingParts = AutoCareStore.getJobCardParts().filter(p => p.status === 'Pending Approval').length;
  const qualityControl = jobCards.filter(j => j.status === 'Testing' || j.progress_percentage >= 85).length;
  const completedToday = jobCards.filter(j => j.kanban_stage === 'COMPLETED' || j.status === 'Ready' || j.status === 'Delivered').length;

  const statCards = document.querySelectorAll('.stats .stat-card strong');
  if (statCards.length >= 4) {
    statCards[0].innerText = totalActive;
    statCards[1].innerText = awaitingParts;
    statCards[2].innerText = qualityControl;
    statCards[3].innerText = completedToday;
  }
}

/**
 * 2. Render Job Cards Table
 */
function renderJobCardsTable() {
  const tbody = document.querySelector('.job-table tbody');
  if (!tbody || typeof AutoCareStore === 'undefined') return;

  let cards = AutoCareStore.getJobCards();

  // Filter
  if (currentStatusFilter !== 'all') {
    if (currentStatusFilter === 'In-Progress') {
      cards = cards.filter(c => c.status === 'Repairing' || c.kanban_stage === 'IN PROGRESS');
    } else if (currentStatusFilter === 'Awaiting-Parts') {
      cards = cards.filter(c => c.status === 'Awaiting Parts' || c.progress_percentage < 40);
    } else if (currentStatusFilter === 'Quality-Control') {
      cards = cards.filter(c => c.status === 'Testing' || c.progress_percentage >= 85);
    }
  }

  // Pagination slice
  const totalItems = cards.length;
  const start = (currentPage - 1) * itemsPerPage;
  const paginated = cards.slice(start, start + itemsPerPage);

  // Update footer text
  const footerText = document.querySelector('.table-footer span');
  if (footerText) {
    footerText.innerText = `Showing ${totalItems > 0 ? start + 1 : 0}-${Math.min(start + itemsPerPage, totalItems)} of ${totalItems} job cards`;
  }

  if (paginated.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No job cards found matching current filter</td></tr>`;
    return;
  }

  let html = '';
  paginated.forEach(card => {
    let statusClass = 'in-progress';
    let progressColor = 'blue-progress';

    if (card.status === 'Awaiting Parts' || card.kanban_stage === 'PENDING') {
      statusClass = 'awaiting';
      progressColor = 'red-progress';
    } else if (card.status === 'Testing' || card.progress_percentage >= 85) {
      statusClass = 'quality';
      progressColor = 'orange-progress';
    } else if (card.kanban_stage === 'COMPLETED' || card.status === 'Ready') {
      statusClass = 'in-progress';
      progressColor = 'green-progress';
    }

    const mechAvatar = card.mechanic_initials ? card.mechanic_initials : 'UA';

    html += `
      <tr data-card-id="${card.id}">
        <td><span class="job-id">${card.code}</span></td>
        <td>
          <div class="customer">
            <strong>${card.customer_name}</strong>
            <span>${card.vehicle_details}</span>
          </div>
        </td>
        <td><span class="date">${card.date_opened}</span></td>
        <td>
          <div class="mechanic">
            <div class="mechanic-avatar initials" style="background: #e2e8f0; color: #334155; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%;">
              ${mechAvatar}
            </div>
            <span>${card.mechanic_name}</span>
          </div>
        </td>
        <td>
          <div class="progress-info">
            <div class="progress-top">
              <span class="status ${statusClass}">${card.status}</span>
              <span class="percentage">${card.progress_percentage}%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill ${progressColor}" style="width:${card.progress_percentage}%;"></div>
            </div>
          </div>
        </td>
        <td><span class="cost">৳${(card.estimated_cost || 0).toLocaleString()}</span></td>
        <td>
          <button class="action-btn" title="Actions" onclick="openJobCardMenu(event, ${card.id})">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

/**
 * 3. Filter Dropdown
 */
function initStatusFilterDropdown() {
  const filterSelect = document.querySelector('.status-filter select.dropdown');
  if (!filterSelect) return;

  filterSelect.addEventListener('change', (e) => {
    currentStatusFilter = e.target.value;
    currentPage = 1;
    renderJobCardsTable();
    showToast(`Filter: ${e.target.options[e.target.selectedIndex].text}`, 'info');
  });
}

/**
 * 4. Create New Job Card
 */
function initCreateJobCardButton() {
  const btn = document.querySelector('.heading-actions .create-btn');
  if (!btn) return;

  btn.addEventListener('click', () => {
    const mechanics = AutoCareStore.getMechanics();
    const mechOptions = mechanics.map(m => `<option value="${m.id}">${m.name} (${m.specialty})</option>`).join('');

    openModal({
      title: 'Create New Job Card',
      content: `
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Customer Full Name *</label>
            <input type="text" id="jc-modal-customer" placeholder="e.g. Tanvir Ahmed" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Vehicle & License Plate *</label>
            <input type="text" id="jc-modal-vehicle" placeholder="e.g. Mitsubishi Pajero • Dhaka-Metro-Gha-11-2244" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Assign Mechanic</label>
              <select id="jc-modal-mechanic" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                <option value="">-- Unassigned --</option>
                ${mechOptions}
              </select>
            </div>
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Initial Est. Cost (৳)</label>
              <input type="number" id="jc-modal-cost" value="10000" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            </div>
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Scope of Repair</label>
            <textarea id="jc-modal-desc" rows="2" placeholder="Full service, suspension inspection..." style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;"></textarea>
          </div>
        </div>
      `,
      confirmText: 'Generate Job Card',
      onConfirm: () => {
        const cust = document.getElementById('jc-modal-customer').value.trim();
        const veh = document.getElementById('jc-modal-vehicle').value.trim();
        const mechId = document.getElementById('jc-modal-mechanic').value;
        const cost = parseFloat(document.getElementById('jc-modal-cost').value) || 0;
        const desc = document.getElementById('jc-modal-desc').value.trim();

        if (!cust || !veh) {
          showToast('Customer and vehicle are required!', 'warning');
          return;
        }

        const mech = mechId ? AutoCareStore.getMechanicById(mechId) : null;

        const newCard = AutoCareStore.createJobCard({
          customer_name: cust,
          vehicle_details: veh,
          vehicle_title: veh.split('•')[0].trim(),
          estimated_cost: cost,
          service_text: desc || 'Repair Work',
          mechanic_id: mech ? mech.id : null,
          mechanic_name: mech ? mech.name : 'Unassigned',
          mechanic_initials: mech ? mech.name.split(' ').map(n=>n[0]).join('') : 'UA',
          status: 'Diagnosis',
          kanban_stage: 'PENDING'
        });

        if (mech) {
          AutoCareStore.updateMechanicWorkload(mech.id, (mech.workload || 0) + 20, 'Busy');
        }

        showToast(`Job Card ${newCard.code} generated successfully!`, 'success');
        renderJobCardsStats();
        renderJobCardsTable();
      }
    });
  });
}

/**
 * 5. Job Card Row Actions Context Menu
 */
window.openJobCardMenu = function (event, cardId, btnElement) {
  event.stopPropagation();
  let card = AutoCareStore.getJobCardById(cardId);

  if (!card && btnElement) {
    const tr = btnElement.closest('tr');
    if (tr) {
      card = {
        id: cardId,
        code: tr.querySelector('.job-id') ? tr.querySelector('.job-id').innerText : `JC-${cardId}`,
        customer_name: tr.querySelector('.customer strong') ? tr.querySelector('.customer strong').innerText : 'Customer',
        vehicle_details: tr.querySelector('.customer span') ? tr.querySelector('.customer span').innerText : 'Vehicle',
        mechanic_name: tr.querySelector('.mechanic span') ? tr.querySelector('.mechanic span').innerText : 'Unassigned',
        status: tr.querySelector('.status') ? tr.querySelector('.status').innerText : 'Unknown',
        progress_percentage: tr.querySelector('.percentage') ? tr.querySelector('.percentage').innerText.replace('%', '') : 0
      };
      
      // Inject into the local store so actions (like status update) can find and persist it
      if (typeof AutoCareStore !== 'undefined' && AutoCareStore.data.jobCards) {
          AutoCareStore.data.jobCards.push(card);
      }
    }
  }

  if (!card) return;

  openModal({
    title: `Manage Job Card: ${card.code}`,
    content: `
      <div style="font-size: 13.5px; line-height: 1.6;">
        <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 14px;">
          <div><strong>Customer:</strong> ${card.customer_name}</div>
          <div><strong>Vehicle:</strong> ${card.vehicle_details}</div>
          <div><strong>Current Mechanic:</strong> ${card.mechanic_name}</div>
          <div><strong>Status:</strong> <span style="font-weight: 600; color: #2563eb;">${card.status} (${card.progress_percentage}%)</span></div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px;">
          <button id="btn-menu-stage" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            🔄 Update Repair Stage (Diagnosis / In Progress / Quality Control / Ready)
          </button>
          <button id="btn-menu-tracker" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            📊 View in Process Tracker
          </button>
          <button id="btn-menu-cost" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            💰 Cost Estimation & Parts
          </button>
          <button id="btn-menu-chat" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            💬 Open Chat & Communications
          </button>
          <button id="btn-menu-reassign" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            👤 Assign / Reassign Mechanic
          </button>
          <button id="btn-menu-invoice" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; text-align: left; cursor: pointer;">
            📄 Generate Customer Invoice
          </button>
        </div>
      </div>
    `,
    confirmText: 'Done',
    cancelText: '',
    onConfirm: () => {}
  });

  // Attach sub-action listeners inside modal
  setTimeout(() => {
    const stageBtn = document.getElementById('btn-menu-stage');
    const trackerBtn = document.getElementById('btn-menu-tracker');
    const costBtn = document.getElementById('btn-menu-cost');
    const chatBtn = document.getElementById('btn-menu-chat');
    const reassignBtn = document.getElementById('btn-menu-reassign');
    const invoiceBtn = document.getElementById('btn-menu-invoice');

    if (trackerBtn) {
      trackerBtn.addEventListener('click', () => {
        window.location.href = 'manager-process-tracker.html';
      });
    }

    if (costBtn) {
      costBtn.addEventListener('click', () => {
        window.location.href = 'manager-cost-estimation.html';
      });
    }

    if (chatBtn) {
      chatBtn.addEventListener('click', () => {
        window.location.href = 'manager-chat.html';
      });
    }

    if (stageBtn) {
      stageBtn.addEventListener('click', () => {
        openModal({
          title: `Update Stage: ${card.code}`,
          content: `
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Select New Stage</label>
              <select id="modal-new-stage" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                <option value="Diagnosis" ${card.status === 'Diagnosis' ? 'selected' : ''}>Diagnosis (Pending)</option>
                <option value="Repairing" ${card.status === 'Repairing' ? 'selected' : ''}>Repairing (In Progress)</option>
                <option value="Testing" ${card.status === 'Testing' ? 'selected' : ''}>Quality Control & Testing</option>
                <option value="Ready" ${card.status === 'Ready' ? 'selected' : ''}>Ready for Handover (Completed)</option>
              </select>
            </div>
          `,
          confirmText: 'Save Stage',
          onConfirm: () => {
            const newStage = document.getElementById('modal-new-stage').value;
            let kanban = 'IN PROGRESS';
            if (newStage === 'Diagnosis') kanban = 'PENDING';
            if (newStage === 'Ready') kanban = 'COMPLETED';

            AutoCareStore.updateJobCardStatus(card.id, newStage, kanban);
            showToast(`${card.code} moved to ${newStage}`, 'success');
            renderJobCardsStats();
            renderJobCardsTable();
          }
        });
      });
    }

    if (reassignBtn) {
      reassignBtn.addEventListener('click', () => {
        const mechs = AutoCareStore.getMechanics();
        const options = mechs.map(m => `<option value="${m.id}" ${card.mechanic_id === m.id ? 'selected' : ''}>${m.name} (${m.specialty})</option>`).join('');

        openModal({
          title: `Reassign Mechanic: ${card.code}`,
          content: `
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Mechanic</label>
              <select id="modal-reassign-mech" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                ${options}
              </select>
            </div>
          `,
          confirmText: 'Confirm Assignment',
          onConfirm: () => {
            const mId = document.getElementById('modal-reassign-mech').value;
            AutoCareStore.assignJobCardMechanic(card.id, mId);
            showToast(`Reassigned to ${AutoCareStore.getMechanicById(mId).name}`, 'success');
            renderJobCardsTable();
          }
        });
      });
    }

    if (invoiceBtn) {
      invoiceBtn.addEventListener('click', () => {
        const newInv = AutoCareStore.createInvoice({
          job_card_id: card.id,
          customer_name: card.customer_name,
          customer_email: 'billing@customer.com',
          vehicle_name: card.vehicle_title || card.vehicle_details,
          vin: card.vin,
          total_amount: card.estimated_cost || 5000,
          status: 'Pending'
        });

        showToast(`Invoice #${newInv.invoice_number} created!`, 'success');
        setTimeout(() => {
          window.location.href = 'manager-invoice-management.html';
        }, 1200);
      });
    }
  }, 100);
};

/**
 * 6. Pagination Controls
 */
function initPagination() {
  const pageNumbers = document.querySelectorAll('.pagination .page-number');
  const arrows = document.querySelectorAll('.pagination .page-arrow');

  pageNumbers.forEach((btn, index) => {
    btn.addEventListener('click', () => {
      pageNumbers.forEach(b => b.classList.remove('active-page'));
      btn.classList.add('active-page');
      currentPage = index + 1;
      renderJobCardsTable();
    });
  });

  if (arrows.length >= 2) {
    // Prev
    arrows[0].addEventListener('click', () => {
      if (currentPage > 1) {
        currentPage--;
        updatePaginationUI();
        renderJobCardsTable();
      }
    });

    // Next
    arrows[1].addEventListener('click', () => {
      currentPage++;
      updatePaginationUI();
      renderJobCardsTable();
    });
  }
}

function updatePaginationUI() {
  const pageNumbers = document.querySelectorAll('.pagination .page-number');
  pageNumbers.forEach((btn, idx) => {
    btn.classList.toggle('active-page', idx + 1 === currentPage);
  });
}
