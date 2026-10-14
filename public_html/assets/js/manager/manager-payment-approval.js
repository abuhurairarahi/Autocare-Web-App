/**
 * manager-payment-approval.js
 * Comprehensive logic for Spare Parts & Mechanic Payment/Part Approvals:
 * - Inventory & Request live stats calculation
 * - Active tab filtering: Pending Approval, Approved, Rejected, All
 * - Live real-time search across parts, mechanics, and work orders
 * - Approve / Reject single requests with real inventory deduction
 * - "Approve All" bulk action
 * - "+ New Request" modal to dynamically request parts for any job card
 * - "Reset Demo Data" helper to restore sample pending requests anytime
 */

let activeStatusFilter = 'Pending Approval';
let searchQuery = '';
let currentSort = 'newest';

document.addEventListener('DOMContentLoaded', () => {
  ensureInitialData();
  renderApprovalStats();
  updateTabBadges();
  renderApprovalTable();
  initTabListeners();
  initSearchListener();
  initApprovalButtons();
});

window.addEventListener('autocare:store:synced', () => {
  renderApprovalStats();
  updateTabBadges();
  renderApprovalTable();
});

/**
 * Ensure initial requests exist so the table is never empty on clean load
 */
function ensureInitialData() {
  if (typeof AutoCareStore === 'undefined') return;
  const parts = AutoCareStore.getJobCardParts();
  const pending = parts ? parts.filter(p => p.status === 'Pending Approval') : [];
  // If no requests exist or if the sample items were all approved in a previous test session,
  // restore the sample pending requests so they are immediately visible.
  if (!parts || parts.length === 0 || (pending.length === 0 && parts.every(p => p.id <= 3))) {
    AutoCareStore.resetJobCardParts();
  }
}

/**
 * 1. Render Stats Cards
 */
function renderApprovalStats() {
  if (typeof AutoCareStore === 'undefined') return;

  const spareParts = AutoCareStore.getSpareParts();
  const requests = AutoCareStore.getJobCardParts();

  const totalInStock = spareParts.reduce((sum, p) => sum + (Number(p.quantity_in_stock) || 0), 0);
  const lowStockCount = spareParts.filter(p => (Number(p.quantity_in_stock) || 0) <= (Number(p.low_stock_threshold) || 5)).length;

  const pendingRequests = requests.filter(r => r.status === 'Pending Approval');
  const todayValue = pendingRequests.reduce((sum, r) => sum + (Number(r.total_price) || 0), 0);

  const statCards = document.querySelectorAll('.stats-grid .stat-card');
  if (statCards.length >= 3) {
    // Card 1: Total Available Parts
    const card1Val = statCards[0].querySelector('.stat-value');
    if (card1Val) card1Val.innerText = totalInStock.toLocaleString();

    // Card 2: Low Stock Alerts
    const card2Val = statCards[1].querySelector('.stat-value');
    if (card2Val) card2Val.innerText = lowStockCount;

    // Card 3: Today's Requests
    const card3Val = statCards[2].querySelector('.stat-value');
    if (card3Val) card3Val.innerText = pendingRequests.length;

    const sub = statCards[2].querySelector('.stat-value-sub');
    if (sub) sub.innerText = `Value: ৳${todayValue.toLocaleString()}`;

    const tag = statCards[2].querySelector('.badge-tag');
    if (tag) tag.innerText = `Value: ৳${todayValue.toLocaleString()}`;
  }
}

/**
 * 2. Update Tab Badges Count
 */
function updateTabBadges() {
  if (typeof AutoCareStore === 'undefined') return;

  const requests = AutoCareStore.getJobCardParts();
  const pendingCount = requests.filter(r => r.status === 'Pending Approval').length;
  const approvedCount = requests.filter(r => r.status === 'Approved').length;
  const rejectedCount = requests.filter(r => r.status === 'Rejected').length;
  const allCount = requests.length;

  const badgePending = document.getElementById('badge-count-pending');
  const badgeApproved = document.getElementById('badge-count-approved');
  const badgeRejected = document.getElementById('badge-count-rejected');
  const badgeAll = document.getElementById('badge-count-all');

  if (badgePending) badgePending.innerText = pendingCount;
  if (badgeApproved) badgeApproved.innerText = approvedCount;
  if (badgeRejected) badgeRejected.innerText = rejectedCount;
  if (badgeAll) badgeAll.innerText = allCount;
}

/**
 * 3. Render Approval Table with Filtering & Searching
 */
function renderApprovalTable() {
  const tbody = document.getElementById('approval-table-body') || document.querySelector('.table-card table tbody');
  const footerText = document.getElementById('approval-showing-text') || document.querySelector('.table-footer .showing-text');
  if (!tbody || typeof AutoCareStore === 'undefined') return;

  let requests = AutoCareStore.getJobCardParts();

  // Filter by status tab
  if (activeStatusFilter !== 'all') {
    requests = requests.filter(r => r.status === activeStatusFilter);
  }

  // Filter by search query
  if (searchQuery) {
    const q = searchQuery.toLowerCase();
    requests = requests.filter(r =>
      (r.part_name && r.part_name.toLowerCase().includes(q)) ||
      (r.part_number && r.part_number.toLowerCase().includes(q)) ||
      (r.mechanic_name && r.mechanic_name.toLowerCase().includes(q)) ||
      (r.work_order && r.work_order.toLowerCase().includes(q))
    );
  }

  // Sort
  if (currentSort === 'newest') {
    requests.sort((a, b) => (b.id || 0) - (a.id || 0));
  } else if (currentSort === 'highest') {
    requests.sort((a, b) => (b.total_price || 0) - (a.total_price || 0));
  } else if (currentSort === 'lowest') {
    requests.sort((a, b) => (a.total_price || 0) - (b.total_price || 0));
  }

  // Update footer text
  if (footerText) {
    const filterLabel = activeStatusFilter === 'all' ? 'total' : activeStatusFilter.toLowerCase();
    footerText.innerText = requests.length > 0
      ? `Showing 1-${requests.length} of ${requests.length} ${filterLabel} requests`
      : `0 ${filterLabel} requests found`;
  }

  // Empty state handling
  if (requests.length === 0) {
    let emptyMsg = 'No requests found matching your filter.';
    let actionButtons = `
      <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; margin-top: 15px;">
        <button class="btn-action" onclick="switchFilter('all')" style="background: #e2e8f0; color: #1e293b; padding: 7px 14px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer;">View All Requests</button>
        <button class="btn-action" onclick="openNewPartRequestModal()" style="background: #2563eb; color: #ffffff; padding: 7px 14px; border-radius: 6px; font-weight: 600; border: none; cursor: pointer;">+ New Request</button>
        <button class="btn-action" onclick="handleResetSampleRequests()" style="background: #f8fafc; color: #2563eb; padding: 7px 14px; border-radius: 6px; font-weight: 600; border: 1px solid #cbd5e1; cursor: pointer;">🔄 Reset Demo Requests</button>
      </div>
    `;

    if (activeStatusFilter === 'Pending Approval') {
      emptyMsg = '🎉 All spare parts requests have been reviewed!';
      const totalAll = AutoCareStore.getJobCardParts().length;
      if (totalAll === 0) {
        emptyMsg = 'No spare parts requests found in database.';
      }
    }

    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state-card">
            <div style="font-size: 34px;">📦</div>
            <h3>${emptyMsg}</h3>
            <p>You can create a new mechanic request or restore sample data to test approvals.</p>
            ${actionButtons}
          </div>
        </td>
      </tr>
    `;
    return;
  }

  // Build rows HTML
  let html = '';
  requests.forEach(req => {
    const unitPrice = Number(req.unit_price) || 0;
    const totalPrice = Number(req.total_price) || (unitPrice * (req.quantity || 1));
    const initials = req.mechanic_initials || (req.mechanic_name ? req.mechanic_name.split(' ').map(n => n[0]).join('') : 'ME');

    // Status Badge
    let statusBadge = '';
    if (req.status === 'Approved') {
      statusBadge = `<span class="status-badge approved"><i class="fa-solid fa-check"></i> Approved</span>`;
    } else if (req.status === 'Rejected') {
      statusBadge = `<span class="status-badge rejected" title="${req.rejection_reason || 'Declined'}"><i class="fa-solid fa-xmark"></i> Rejected</span>`;
    } else {
      statusBadge = `<span class="status-badge pending"><i class="fa-regular fa-clock"></i> Pending</span>`;
    }

    // Actions cell
    let actionsCell = '';
    if (req.status === 'Pending Approval') {
      actionsCell = `
        <button class="btn-action approve" onclick="handleApprovePart(${req.id})">✓ Approve</button>
        <button class="btn-action reject" onclick="handleRejectPart(${req.id})">✕ Reject</button>
      `;
    } else if (req.status === 'Approved') {
      actionsCell = `<span style="color: #16a34a; font-size: 11.5px; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Stock Deducted</span>`;
    } else {
      actionsCell = `<span style="color: #ef4444; font-size: 11.5px; font-weight: 600;"><i class="fa-solid fa-ban"></i> Declined</span>`;
    }

    html += `
      <tr data-part-req-id="${req.id}">
        <td>
          <div class="part-cell">
            <div class="part-icon">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            </div>
            <div>
              <div class="part-name">${escapeHtml(req.part_name || 'Spare Part')}</div>
              <div class="part-pn">${escapeHtml(req.part_number || 'N/A')}</div>
            </div>
          </div>
        </td>
        <td><span class="qty-badge">${req.quantity || 1} ${escapeHtml(req.unit || 'Units')}</span></td>
        <td class="dim-text">৳${unitPrice.toFixed(2)}</td>
        <td class="bold">৳${totalPrice.toFixed(2)}</td>
        <td>
          <div class="mechanic-cell">
            <div class="avatar-sm navy">${escapeHtml(initials)}</div>
            <div>
              <div class="mechanic-name">${escapeHtml(req.mechanic_name || 'Mechanic')}</div>
              <div class="job-id" onclick="window.location.href='manager-jobCards.html';" style="cursor: pointer; text-decoration: underline;" title="View Job Card">Job: ${escapeHtml(req.work_order || '#WO-General')}</div>
            </div>
          </div>
        </td>
        <td>${statusBadge}</td>
        <td class="actions-td">${actionsCell}</td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

/**
 * 4. Tab Listener Setup
 */
function initTabListeners() {
  const tabs = document.querySelectorAll('#approval-status-tabs .tab-btn');
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      activeStatusFilter = tab.getAttribute('data-status');
      renderApprovalTable();
    });
  });
}

window.switchFilter = function (status) {
  activeStatusFilter = status;
  const tabs = document.querySelectorAll('#approval-status-tabs .tab-btn');
  tabs.forEach(tab => {
    if (tab.getAttribute('data-status') === status) {
      tab.classList.add('active');
    } else {
      tab.classList.remove('active');
    }
  });
  renderApprovalTable();
};

/**
 * 5. Search Listener Setup
 */
function initSearchListener() {
  const searchInput = document.getElementById('approval-search-input');
  if (!searchInput) return;

  searchInput.addEventListener('input', (e) => {
    searchQuery = e.target.value.trim();
    renderApprovalTable();
  });
}

/**
 * 6. Action Handlers (Approve, Reject, Approve All, Reset, Add New)
 */
window.handleApprovePart = function (reqId) {
  const req = AutoCareStore.getJobCardPartById(reqId);
  if (!req) return;

  AutoCareStore.approvePartRequest(reqId);
  if (typeof showToast === 'function') {
    showToast(`Approved ${req.part_name} for ${req.work_order}! Stock deducted.`, 'success');
  }

  renderApprovalStats();
  updateTabBadges();
  renderApprovalTable();
};

window.handleRejectPart = function (reqId) {
  const req = AutoCareStore.getJobCardPartById(reqId);
  if (!req) return;

  if (typeof openModal === 'function') {
    openModal({
      title: `Reject Request: ${req.part_name}`,
      content: `
        <p style="margin-bottom: 12px; color: #475569;">Are you sure you want to decline this mechanic part request?</p>
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px; color: #1e293b;">Select Reason</label>
          <select id="modal-reject-part-reason" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            <option>Part currently out of stock</option>
            <option>Alternative aftermarket part required</option>
            <option>Repair estimate limit exceeded</option>
            <option>Incorrect specification for vehicle</option>
          </select>
        </div>
      `,
      confirmText: 'Confirm Reject',
      onConfirm: () => {
        const select = document.getElementById('modal-reject-part-reason');
        const reason = select ? select.value : 'Declined by manager';
        AutoCareStore.rejectPartRequest(reqId, reason);
        if (typeof showToast === 'function') {
          showToast(`Declined request for ${req.part_name}`, 'warning');
        }
        renderApprovalStats();
        updateTabBadges();
        renderApprovalTable();
      }
    });
  } else {
    AutoCareStore.rejectPartRequest(reqId, 'Declined by manager');
    renderApprovalStats();
    updateTabBadges();
    renderApprovalTable();
  }
};

window.handleResetSampleRequests = function () {
  if (typeof openModal === 'function') {
    openModal({
      title: 'Reset Demo Requests',
      content: `
        <p style="margin-bottom: 8px;">This will reload the initial sample spare parts requests with <strong>Pending Approval</strong> status.</p>
        <p style="color: #64748b; font-size: 12.5px;">Useful for testing approvals, inventory deductions, and notifications.</p>
      `,
      confirmText: 'Reset Requests',
      onConfirm: () => {
        AutoCareStore.resetJobCardParts();
        switchFilter('Pending Approval');
        renderApprovalStats();
        updateTabBadges();
        renderApprovalTable();
        if (typeof showToast === 'function') {
          showToast('Sample pending requests successfully restored!', 'success');
        }
      }
    });
  } else {
    AutoCareStore.resetJobCardParts();
    switchFilter('Pending Approval');
    renderApprovalStats();
    updateTabBadges();
    renderApprovalTable();
  }
};

window.openNewPartRequestModal = function () {
  const jobCards = AutoCareStore.getJobCards();
  const spareParts = AutoCareStore.getSpareParts();
  const mechanics = AutoCareStore.getMechanics();

  let jobCardOptions = jobCards.map(j => `<option value="${j.id}">${j.work_order || j.code} - ${j.vehicle_title || j.customer_name}</option>`).join('');
  let partOptions = spareParts.map(p => `<option value="${p.id}" data-price="${p.price}" data-pn="${p.part_number}">${p.name} (Stock: ${p.quantity_in_stock}) - ৳${p.price}</option>`).join('');
  let mechanicOptions = mechanics.map(m => `<option value="${m.name}">${m.name} (${m.specialty || 'Mechanic'})</option>`).join('');

  if (typeof openModal === 'function') {
    openModal({
      title: 'Create New Spare Part Request',
      content: `
        <div style="display: flex; flex-direction: column; gap: 14px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 12.5px; margin-bottom: 4px; color: #334155;">Select Job Card</label>
            <select id="modal-new-job-card" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              ${jobCardOptions}
            </select>
          </div>

          <div>
            <label style="display: block; font-weight: 600; font-size: 12.5px; margin-bottom: 4px; color: #334155;">Select Spare Part</label>
            <select id="modal-new-part" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              ${partOptions}
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div>
              <label style="display: block; font-weight: 600; font-size: 12.5px; margin-bottom: 4px; color: #334155;">Quantity</label>
              <input type="number" id="modal-new-qty" value="1" min="1" max="100" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            </div>
            <div>
              <label style="display: block; font-weight: 600; font-size: 12.5px; margin-bottom: 4px; color: #334155;">Requesting Mechanic</label>
              <select id="modal-new-mechanic" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                ${mechanicOptions}
              </select>
            </div>
          </div>
        </div>
      `,
      confirmText: 'Submit Request',
      onConfirm: () => {
        const jobCardId = document.getElementById('modal-new-job-card').value;
        const partSelect = document.getElementById('modal-new-part');
        const partId = Number(partSelect.value);
        const selectedOption = partSelect.selectedOptions[0];
        const unitPrice = Number(selectedOption.getAttribute('data-price')) || 50;
        const partPn = selectedOption.getAttribute('data-pn') || 'PN: OEM';
        const partName = selectedOption.text.split(' (Stock:')[0];
        const qty = parseInt(document.getElementById('modal-new-qty').value, 10) || 1;
        const mechName = document.getElementById('modal-new-mechanic').value;

        const jobCard = AutoCareStore.getJobCardById(jobCardId);
        const workOrder = jobCard ? (jobCard.work_order || jobCard.code) : '#WO-9950';

        AutoCareStore.createJobCardPart({
          job_card_id: Number(jobCardId),
          work_order: workOrder,
          part_id: partId,
          part_name: partName,
          part_number: partPn.startsWith('PN:') ? partPn : `PN: ${partPn}`,
          quantity: qty,
          unit: qty > 1 ? 'Units' : 'Unit',
          unit_price: unitPrice,
          total_price: unitPrice * qty,
          mechanic_name: mechName,
          mechanic_initials: mechName.split(' ').map(n => n[0]).join(''),
          status: 'Pending Approval'
        });

        switchFilter('Pending Approval');
        renderApprovalStats();
        updateTabBadges();
        renderApprovalTable();
        if (typeof showToast === 'function') {
          showToast(`New request created for ${partName} (${workOrder})!`, 'success');
        }
      }
    });
  }
};

/**
 * 7. Button Initialization
 */
function initApprovalButtons() {
  // Approve All button
  const approveAllBtn = document.getElementById('btn-approve-all-requests') || document.querySelector('.header-actions .btn-approve-all');
  if (approveAllBtn) {
    approveAllBtn.addEventListener('click', () => {
      const count = AutoCareStore.approveAllPartRequests();
      if (count > 0) {
        if (typeof showToast === 'function') {
          showToast(`All ${count} spare parts requests approved and inventory deducted!`, 'success');
        }
      } else {
        if (typeof showToast === 'function') {
          showToast('No pending requests to approve.', 'info');
        }
      }
      renderApprovalStats();
      updateTabBadges();
      renderApprovalTable();
    });
  }

  // Filter button
  const filterBtn = document.getElementById('btn-filter-modal') || document.querySelector('.header-actions .btn-secondary');
  if (filterBtn) {
    filterBtn.addEventListener('click', () => {
      if (typeof openModal === 'function') {
        openModal({
          title: 'Filter & Sort Requests',
          content: `
            <div style="display: flex; flex-direction: column; gap: 14px;">
              <div>
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Filter by Status</label>
                <select id="modal-filter-status" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                  <option value="Pending Approval" ${activeStatusFilter === 'Pending Approval' ? 'selected' : ''}>Pending Approval Only</option>
                  <option value="Approved" ${activeStatusFilter === 'Approved' ? 'selected' : ''}>Approved Only</option>
                  <option value="Rejected" ${activeStatusFilter === 'Rejected' ? 'selected' : ''}>Rejected Only</option>
                  <option value="all" ${activeStatusFilter === 'all' ? 'selected' : ''}>Show All Requests</option>
                </select>
              </div>

              <div>
                <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Sort Order</label>
                <select id="modal-filter-sort" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                  <option value="newest" ${currentSort === 'newest' ? 'selected' : ''}>Newest First</option>
                  <option value="highest" ${currentSort === 'highest' ? 'selected' : ''}>Highest Total Amount</option>
                  <option value="lowest" ${currentSort === 'lowest' ? 'selected' : ''}>Lowest Total Amount</option>
                </select>
              </div>
            </div>
          `,
          confirmText: 'Apply Filter',
          onConfirm: () => {
            const statusSelect = document.getElementById('modal-filter-status');
            const sortSelect = document.getElementById('modal-filter-sort');
            if (statusSelect) switchFilter(statusSelect.value);
            if (sortSelect) currentSort = sortSelect.value;
            renderApprovalTable();
            if (typeof showToast === 'function') {
              showToast('Filters applied successfully', 'info');
            }
          }
        });
      }
    });
  }

  // + New Request button
  const newReqBtn = document.getElementById('btn-open-request-modal');
  if (newReqBtn) {
    newReqBtn.addEventListener('click', openNewPartRequestModal);
  }

  // Reset button
  const resetBtn = document.getElementById('btn-reset-sample-data');
  if (resetBtn) {
    resetBtn.addEventListener('click', handleResetSampleRequests);
  }
}

/**
 * Utility: HTML escape
 */
function escapeHtml(str) {
  if (typeof str !== 'string') return String(str || '');
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
