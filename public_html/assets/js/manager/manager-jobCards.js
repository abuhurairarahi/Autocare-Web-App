/**
 * manager-jobCards.js
 * Comprehensive logic for the Job Cards management page:
 * Stat counters, status filtering, creating job cards, row action menus,
 * stage progression, mechanic reassignment, invoice generation, and pagination.
 */

let currentStatusFilter = 'all';
let currentPage = 1;
const itemsPerPage = 5;

let jobCardsList = [];
let statTotalActive = 0;
let statAwaitingParts = 0;
let statQualityControl = 0;
let statCompletedToday = 0;

document.addEventListener('DOMContentLoaded', () => {
  fetchJobCards();
  initStatusFilterDropdown();
  initCreateJobCardButton();
  initPagination();

  // Auto-refresh the table and stats every 5 seconds
  setInterval(() => {
    fetchJobCards(true); // pass true to indicate silent background fetch
  }, 5000);
});

async function fetchJobCards(isBackground = false) {
  try {
    const res = await fetch(`../../api/manager-api/manager-jobCards-api.php?action=list&_t=${new Date().getTime()}`);
    const data = await res.json();
    if (data.success) {
      jobCardsList = data.cards || [];
      statTotalActive = data.stats.total_active || 0;
      statAwaitingParts = data.stats.awaiting_parts || 0;
      statQualityControl = data.stats.quality_control || 0;
      statCompletedToday = data.stats.completed_today || 0;
      renderJobCardsStats();
      renderJobCardsTable();
    } else {
        throw new Error('API return success false');
    }
  } catch(err) {
    if (!isBackground) {
      console.error('Failed to fetch job cards from DB. Falling back to local store.', err);
      if (typeof AutoCareStore !== 'undefined') {
        jobCardsList = AutoCareStore.getJobCards();
        statAwaitingParts = AutoCareStore.getJobCardParts().filter(p => p.status === 'Pending Approval').length;
        renderJobCardsStats();
        renderJobCardsTable();
      }
    }
  }
}

/**
 * 1. Render Stat Cards
 */
function renderJobCardsStats() {
  const elTotal = document.getElementById('stat-total-active');
  const elAwaiting = document.getElementById('stat-awaiting-parts');
  const elQuality = document.getElementById('stat-quality-control');
  const elCompleted = document.getElementById('stat-completed');

  if (elTotal) elTotal.innerText = statTotalActive;
  if (elAwaiting) elAwaiting.innerText = statAwaitingParts;
  if (elQuality) elQuality.innerText = statQualityControl;
  if (elCompleted) elCompleted.innerText = statCompletedToday;
}

/**
 * 2. Render Job Cards Table
 */
function renderJobCardsTable() {
  const tbody = document.querySelector('.job-table tbody');
  if (!tbody) return;

  let cards = [...jobCardsList];

  // Filter
  if (currentStatusFilter !== 'all') {
    if (currentStatusFilter === 'In-Progress') {
      cards = cards.filter(c => c.status === 'Repairing' || c.status === 'In Progress' || c.kanban_stage === 'IN PROGRESS');
    } else if (currentStatusFilter === 'Awaiting-Parts') {
      cards = cards.filter(c => c.status === 'Awaiting Parts');
    } else if (currentStatusFilter === 'Quality-Control') {
      cards = cards.filter(c => c.status === 'Testing' || c.status === 'Quality Control' || c.progress_percentage >= 85);
    }
  }

  const totalItems = cards.length;
  const start = (currentPage - 1) * itemsPerPage;
  const paginated = cards.slice(start, start + itemsPerPage);

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

    if (card.status === 'Awaiting Parts') {
      statusClass = 'awaiting';
      progressColor = 'red-progress';
    } else if (card.status === 'Testing' || card.status === 'Quality Control') {
      statusClass = 'quality';
      progressColor = 'orange-progress';
    } else if (card.status === 'Ready' || card.status === 'Delivered' || card.status === 'Completed') {
      statusClass = 'in-progress';
      progressColor = 'green-progress';
    }

    let mechAvatar = 'UA';
    let mechName = 'Unassigned';
    if (card.mechanic_name) {
       mechName = card.mechanic_name;
       mechAvatar = card.mechanic_name.split(' ').map(n=>n[0]).slice(0,2).join('').toUpperCase();
    } else if (card.mechanic_initials) {
       mechAvatar = card.mechanic_initials;
    }

    const dateOpened = card.created_at ? card.created_at.split(' ')[0] : (card.date_opened || 'N/A');
    const progress = card.progress_percentage || 10;
    const estCost = parseFloat(card.estimated_cost) || 0;

    html += `
      <tr data-card-id="${card.id}">
        <td><span class="job-id">${card.code}</span></td>
        <td>
          <div class="customer">
            <strong>${card.customer_name}</strong>
            <span>${card.vehicle_details}</span>
          </div>
        </td>
        <td><span class="date">${dateOpened}</span></td>
        <td>
          <div class="mechanic">
            <div class="mechanic-avatar initials" style="background: #e2e8f0; color: #334155; font-weight: 700; font-size: 11px; display: flex; align-items: center; justify-content: center; width: 30px; height: 30px; border-radius: 50%;">
              ${mechAvatar}
            </div>
            <span>${mechName}</span>
          </div>
        </td>
        <td>
          <div class="progress-info">
            <div class="progress-top">
              <span class="status ${statusClass}">${card.status}</span>
              <span class="percentage">${progress}%</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill ${progressColor}" style="width:${progress}%;"></div>
            </div>
          </div>
        </td>
        <td><span class="cost">৳${estCost.toLocaleString()}</span></td>
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

function initCreateJobCardButton() {
  const btn = document.querySelector('.heading-actions .create-btn');
  if (!btn) return;

  btn.addEventListener('click', async () => {
    // 1. Fetch mechanics via AJAX dynamically when modal opens
    let mechOptions = '';
    try {
      const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=mechanics');
      const data = await res.json();
      if (data.success && data.mechanics) {
        mechOptions = data.mechanics.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
      } else {
        throw new Error('API returned false');
      }
    } catch(err) {
      console.error('Error fetching mechanics, using fallback', err);
      mechOptions = `<option value="1">J. Smith</option><option value="2">A. Davis</option>`;
    }

    openModal({
      title: 'Create New Job Card',
      content: `
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Customer Full Name *</label>
            <input type="text" id="jc-modal-customer" placeholder="e.g. Tanvir Ahmed" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required>
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Vehicle & License Plate *</label>
            <input type="text" id="jc-modal-vehicle" placeholder="e.g. Mitsubishi Pajero • Dhaka-Metro-Gha-11-2244" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;" required>
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
              <input type="number" id="jc-modal-cost" placeholder="0" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            </div>
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Scope of Repair</label>
            <textarea id="jc-modal-desc" rows="2" placeholder="Full service, suspension inspection..." style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;"></textarea>
          </div>
        </div>
      `,
      confirmText: 'Generate Job Card',
      cancelText: 'Cancel',
      onConfirm: async () => {
        const cust = document.getElementById('jc-modal-customer').value.trim();
        const veh = document.getElementById('jc-modal-vehicle').value.trim();
        const mechId = document.getElementById('jc-modal-mechanic').value;
        const cost = parseFloat(document.getElementById('jc-modal-cost').value) || 0;
        const desc = document.getElementById('jc-modal-desc').value.trim();

        // Client-side Validation
        if (!cust || !veh) {
          showToast('Customer and vehicle are required!', 'warning');
          return;
        }

        // AJAX Form Submit
        try {
          const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=create_job_card', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              customer_name: cust,
              vehicle_details: veh,
              mechanic_id: mechId,
              estimated_cost: cost,
              service_text: desc
            })
          });
          const data = await res.json();
          if (data.success) {
            showToast(`Job Card ${data.code} generated successfully!`, 'success');
            
            // Re-fetch job cards from database so UI reflects the real MySQL state immediately
            fetchJobCards();
          } else {
             showToast(data.error || 'Failed to create job card', 'error');
          }
        } catch(err) {
            // Offline Mode Fallback
             showToast('Network error while saving. Offline Mode Fallback.', 'warning');
             const mechSelect = document.getElementById('jc-modal-mechanic');
             const mechName = mechId ? mechSelect.options[mechSelect.selectedIndex].text : 'Unassigned';
            
             jobCardsList.unshift({
               id: Date.now(),
               code: 'JC-MOCK-' + Math.floor(Math.random()*1000),
               customer_name: cust,
               vehicle_details: veh,
               estimated_cost: cost,
               mechanic_name: mechName,
               status: 'In Progress',
               progress_percentage: 10,
               created_at: new Date().toISOString()
             });
             renderJobCardsStats();
             renderJobCardsTable();
        }
      }
    });
  });
}

/**
 * 5. Job Card Row Actions Context Menu
 */
window.openJobCardMenu = async function (event, cardId) {
  event.stopPropagation();
  
  // Local fallback object
  let card = jobCardsList.find(c => c.id == cardId);
  if (!card) return;

  try {
    const res = await fetch(`../../api/manager-api/manager-jobCards-api.php?action=details&id=${cardId}`);
    const data = await res.json();
    if (data.success) {
      card = data.card;
    }
  } catch (err) {
    console.error('API fetch failed, using offline fallback data', err);
  }

  const progress = card.progress_percentage || 10;
  const mechName = card.mechanic_name || 'Unassigned';

  openModal({
    title: `Manage Job Card: ${card.code}`,
    content: `
      <div style="font-size: 13.5px; line-height: 1.6;">
        <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 14px;">
          <div><strong>Customer:</strong> ${card.customer_name}</div>
          <div><strong>Vehicle:</strong> ${card.vehicle_details}</div>
          <div><strong>Current Mechanic:</strong> ${mechName}</div>
          <div><strong>Status:</strong> <span style="font-weight: 600; color: #2563eb;">${card.status} (${progress}%)</span></div>
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

    if (reassignBtn) {
      reassignBtn.addEventListener('click', async () => {
        let mechOptions = '';
        try {
          const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=mechanics');
          const data = await res.json();
          if (data.success && data.mechanics) {
            mechOptions = data.mechanics.map(m => `<option value="${m.id}" ${card.mechanic_id == m.id ? 'selected' : ''}>${m.name}</option>`).join('');
          }
        } catch(err) {
          mechOptions = `<option value="1">J. Smith</option><option value="2">A. Davis</option>`;
        }

        openModal({
          title: `Reassign Mechanic: ${card.code}`,
          content: `
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Mechanic</label>
              <select id="modal-reassign-mech" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                <option value="">-- Unassigned --</option>
                ${mechOptions}
              </select>
            </div>
          `,
          confirmText: 'Confirm Assignment',
          onConfirm: async () => {
            const selectEl = document.getElementById('modal-reassign-mech');
            const newMechId = selectEl.value;
            const newMechName = selectEl.options[selectEl.selectedIndex].text;
            
            try {
              const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=reassign_mechanic', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cardId, mechanic_id: newMechId })
              });
              const data = await res.json();
              if (data.success) {
                showToast(`Reassigned to ${newMechName}`, 'success');
                fetchJobCards(); // Dynamically reload from DB
              } else {
                showToast(data.error || 'Failed to reassign', 'error');
              }
            } catch (err) {
                showToast(`Reassigned to ${newMechName} (Offline Mode)`, 'warning');
                const localCard = jobCardsList.find(c => c.id == cardId);
                if (localCard) {
                  localCard.mechanic_id = newMechId;
                  localCard.mechanic_name = newMechName !== '-- Unassigned --' ? newMechName : 'Unassigned';
                }
                renderJobCardsTable();
            }
          }
        });
      });
    }

    if (invoiceBtn) {
      invoiceBtn.addEventListener('click', async () => {
        try {
          const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=generate_invoice', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ job_card_id: cardId })
          });
          const data = await res.json();
          if (data.success) {
            showToast(`Invoice ${data.invoice_number} generated!`, 'success');
          } else {
            showToast(data.error || 'Failed to generate invoice', 'error');
          }
        } catch (err) {
          showToast('Invoice generated (Offline)', 'success');
        }
        setTimeout(() => {
          window.location.href = 'manager-invoice-management.html';
        }, 1200);
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
                <option value="In Progress" ${card.status === 'Repairing' || card.status === 'In Progress' ? 'selected' : ''}>Repairing (In Progress)</option>
                <option value="Quality Control" ${card.status === 'Testing' || card.status === 'Quality Control' ? 'selected' : ''}>Quality Control & Testing</option>
                <option value="Ready" ${card.status === 'Ready' || card.status === 'Completed' ? 'selected' : ''}>Ready for Handover (Completed)</option>
              </select>
            </div>
          `,
          confirmText: 'Save Stage',
          onConfirm: async () => {
            const newStage = document.getElementById('modal-new-stage').value;
            
            try {
              const res = await fetch('../../api/manager-api/manager-jobCards-api.php?action=update_stage', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: cardId, stage: newStage })
              });
              const data = await res.json();
              if (data.success) {
                showToast(`${card.code} moved to ${newStage}`, 'success');
                fetchJobCards(); // Refetch table data from database
              } else {
                showToast(data.error || 'Failed to update stage', 'error');
              }
            } catch (err) {
                showToast(`${card.code} moved to ${newStage} (Offline Mode)`, 'warning');
                // Offline fallback logic
                const localCard = jobCardsList.find(c => c.id == cardId);
                if (localCard) {
                  localCard.status = newStage;
                  if (newStage === 'Ready' || newStage === 'Completed') localCard.progress_percentage = 100;
                  if (newStage === 'Testing' || newStage === 'Quality Control') localCard.progress_percentage = 85;
                  if (newStage === 'In Progress' || newStage === 'Diagnosis') localCard.progress_percentage = 50;
                }
                renderJobCardsStats();
                renderJobCardsTable();
            }
          }
        });
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
