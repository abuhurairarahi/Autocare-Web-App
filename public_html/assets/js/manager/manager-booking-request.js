/**
 * manager-booking-request.js
 * Functionality for the Service Booking Requests page:
 * Row selection, right details panel updates, approving & creating job cards,
 * rejection, history view, filter, sort, and export.
 */

let selectedRequestId = 145; // default selected request ID

document.addEventListener('DOMContentLoaded', () => {
  renderBookingRequestsTable();
  initDetailActionButtons();
  initTopActionControls();
});

window.addEventListener('autocare:store:synced', () => {
  renderBookingRequestsTable();
});

/**
 * 1. Render Requests Table from store
 */
function renderBookingRequestsTable(filterPriority = 'all', sortBy = 'date') {
  if (typeof AutoCareStore === 'undefined') return;

  const tableBody = document.querySelector('.table-panel table tbody');
  const countBadge = document.querySelector('.table-panel .badge-count');
  if (!tableBody) return;

  let requests = AutoCareStore.getServiceRequests().filter(r => r.status === 'Pending');

  // Filter
  if (filterPriority !== 'all') {
    requests = requests.filter(r => r.priority.toLowerCase() === filterPriority.toLowerCase());
  }

  // Sort
  if (sortBy === 'priority') {
    const pWeight = { 'High': 3, 'Normal': 2, 'Low': 1 };
    requests.sort((a, b) => (pWeight[b.priority] || 0) - (pWeight[a.priority] || 0));
  } else {
    // By ID / date
    requests.sort((a, b) => b.id - a.id);
  }

  if (countBadge) {
    countBadge.innerText = `${requests.length} New`;
  }

  if (requests.length === 0) {
    tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No pending booking requests</td></tr>`;
    clearDetailsPanel();
    return;
  }

  // Ensure selectedRequestId is valid
  if (!requests.some(r => r.id === selectedRequestId)) {
    selectedRequestId = requests[0].id;
  }

  let html = '';
  requests.forEach(r => {
    const owner = AutoCareStore.getOwners().find(o => o.id === r.owner_id) || { name: 'Customer' };
    const veh = AutoCareStore.data.vehicles.find(v => v.id === r.vehicle_id) || { model: 'Vehicle', registration_number: 'N/A' };
    const cat = AutoCareStore.data.categories.find(c => c.id === r.category_id) || { name: 'Service' };
    const isSelected = r.id === selectedRequestId;

    const priorityClass = r.priority.toLowerCase() === 'high' ? 'high' : 'normal';

    html += `
      <tr class="${isSelected ? 'selected' : ''}" data-request-id="${r.id}" style="cursor: pointer;">
        <td class="bold">${r.code}</td>
        <td>${owner.name}</td>
        <td>
          <div class="vehicle-cell">
            <span>${veh.model.split(' ')[0]} ${veh.model.split(' ')[1] || ''}</span>
            <small>${veh.registration_number}</small>
          </div>
        </td>
        <td>${cat.name}</td>
        <td>${r.requested_date}</td>
        <td><span class="badge-priority ${priorityClass}">${r.priority}</span></td>
        <td><span class="badge-status">Pending Review</span></td>
      </tr>
    `;
  });

  tableBody.innerHTML = html;

  // Row click listeners
  tableBody.querySelectorAll('tr').forEach(row => {
    row.addEventListener('click', () => {
      const id = Number(row.getAttribute('data-request-id'));
      if (id) {
        selectedRequestId = id;
        tableBody.querySelectorAll('tr').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        updateDetailsPanel(id);
      }
    });
  });

  // Update details panel for currently selected
  updateDetailsPanel(selectedRequestId);
}

/**
 * 2. Update Details Panel (Right sidebar)
 */
function updateDetailsPanel(requestId) {
  const panel = document.querySelector('.details-panel');
  if (!panel || typeof AutoCareStore === 'undefined') return;

  const req = AutoCareStore.getServiceRequestById(requestId);
  if (!req) return;

  const owner = AutoCareStore.getOwners().find(o => o.id === req.owner_id) || { name: 'Unknown Customer', phone: 'N/A' };
  const veh = AutoCareStore.data.vehicles.find(v => v.id === req.vehicle_id) || { model: 'Unknown Vehicle', registration_number: 'N/A', vin: 'N/A' };

  // Update Header
  const titleEl = panel.querySelector('.details-header h2');
  const dateEl = panel.querySelector('.details-header .submitted-date');
  if (titleEl) titleEl.innerText = req.code;
  if (dateEl) dateEl.innerText = `Submitted ${req.created_at || req.requested_date}`;

  // Update Owner
  const avatarEl = panel.querySelector('.owner-details .avatar-box');
  const ownerNameEl = panel.querySelector('.owner-text h3');
  const phoneEl = panel.querySelector('.owner-text p');
  if (avatarEl) {
    avatarEl.innerText = owner.name.split(' ').map(n => n[0]).slice(0, 2).join('');
  }
  if (ownerNameEl) ownerNameEl.innerText = owner.name;
  if (phoneEl) {
    phoneEl.innerHTML = `
      <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
      ${owner.phone}
    `;
  }

  // Update Vehicle
  const vehTitleEl = panel.querySelector('.vehicle-title h3');
  const plateEl = panel.querySelector('.vehicle-specs .spec-val');
  const vinEl = panel.querySelectorAll('.vehicle-specs .spec-val')[1];
  if (vehTitleEl) vehTitleEl.innerText = veh.model;
  if (plateEl) plateEl.innerText = veh.registration_number;
  if (vinEl) vinEl.innerText = veh.vin;

  // Update Service Description
  const descEl = panel.querySelector('.service-description p');
  if (descEl) descEl.innerText = req.description;
}

function clearDetailsPanel() {
  const panel = document.querySelector('.details-panel');
  if (!panel) return;
  const titleEl = panel.querySelector('.details-header h2');
  if (titleEl) titleEl.innerText = 'No Request Selected';
  const descEl = panel.querySelector('.service-description p');
  if (descEl) descEl.innerText = 'Select a booking request from the table to view details.';
}

/**
 * 3. Detail Action Buttons (Approve, Reject, View History)
 */
function initDetailActionButtons() {
  const approveBtn = document.querySelector('.details-panel .btn-approve') || document.querySelector('#btn-approve-request');
  const rejectBtn = document.querySelector('.details-panel .btn-reject') || document.querySelector('#btn-reject-request');
  const historyBtn = document.querySelector('.details-panel .btn-secondary');

  // Approve & Create Job Card
  if (approveBtn) {
    approveBtn.addEventListener('click', () => {
      const req = AutoCareStore.getServiceRequestById(selectedRequestId);
      if (!req) return;

      const mechanics = AutoCareStore.getMechanics();
      const mechOptions = mechanics
        .map(m => `<option value="${m.id}">${m.name} (${m.specialty} - Workload: ${m.workload}%)</option>`)
        .join('');

      openModal({
        title: `Approve Booking ${req.code}`,
        content: `
          <p>You are converting this request into an official <strong>AutoCare Job Card</strong>.</p>
          <div style="margin-top: 14px;">
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Assign Mechanic to Job Card</label>
            <select id="modal-approve-mechanic" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              <option value="">-- Choose Mechanic --</option>
              ${mechOptions}
            </select>
          </div>
          <div style="margin-top: 14px;">
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Target Delivery Date</label>
            <input type="date" id="modal-approve-date" value="${new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0]}" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
        `,
        confirmText: 'Approve & Create Job Card',
        onConfirm: () => {
          const mechId = document.getElementById('modal-approve-mechanic').value;
          const targetDate = document.getElementById('modal-approve-date').value;
          const selectedMech = mechId ? AutoCareStore.getMechanicById(mechId) : null;

          const owner = AutoCareStore.getOwners().find(o => o.id === req.owner_id);
          const veh = AutoCareStore.data.vehicles.find(v => v.id === req.vehicle_id);

          // Create Job Card
          const newCard = AutoCareStore.createJobCard({
            request_id: req.id,
            customer_name: owner ? owner.name : 'Customer',
            vehicle_details: veh ? `${veh.model} • ${veh.registration_number}` : 'Vehicle',
            vehicle_title: veh ? veh.model : 'Vehicle',
            vin: veh ? veh.vin : 'N/A',
            service_text: req.description,
            priority: req.priority,
            delivery_date: targetDate || 'TBD',
            mechanic_id: selectedMech ? selectedMech.id : null,
            mechanic_name: selectedMech ? selectedMech.name : 'Unassigned',
            mechanic_initials: selectedMech ? selectedMech.name.split(' ').map(n=>n[0]).join('') : 'UA',
            status: 'Diagnosis',
            kanban_stage: 'PENDING'
          });

          // Mark request as Approved
          AutoCareStore.updateServiceRequestStatus(req.id, 'Approved');

          if (selectedMech) {
            AutoCareStore.updateMechanicWorkload(selectedMech.id, (selectedMech.workload || 0) + 20, 'Busy');
          }

          showToast(`Job Card ${newCard.code} created for ${req.code}!`, 'success');
          renderBookingRequestsTable();

          setTimeout(() => {
            openModal({
              title: `Job Card ${newCard.code} Created`,
              content: `
                <p>Booking <strong>${req.code}</strong> was successfully approved and converted into Job Card <strong>${newCard.code}</strong>.</p>
                <p>Assigned Technician: <strong>${newCard.mechanic_name}</strong></p>
              `,
              confirmText: 'View in Job Cards',
              cancelText: 'Stay on Requests',
              onConfirm: () => {
                window.location.href = 'manager-jobCards.html';
              }
            });
          }, 400);
        }
      });
    });
  }

  // Reject Request
  if (rejectBtn) {
    rejectBtn.addEventListener('click', () => {
      const req = AutoCareStore.getServiceRequestById(selectedRequestId);
      if (!req) return;

      openModal({
        title: `Reject Booking Request ${req.code}`,
        content: `
          <p>Are you sure you want to decline this booking request?</p>
          <div style="margin-top: 14px;">
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Reason for Rejection</label>
            <select id="modal-reject-reason" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              <option>Workshop fully booked on requested date</option>
              <option>Parts currently unavailable / backordered</option>
              <option>Service outside workshop scope</option>
              <option>Customer request cancelled</option>
            </select>
          </div>
        `,
        confirmText: 'Confirm Rejection',
        cancelText: 'Cancel',
        onConfirm: () => {
          AutoCareStore.updateServiceRequestStatus(req.id, 'Rejected');
          showToast(`Request ${req.code} has been rejected.`, 'info');
          renderBookingRequestsTable();
        }
      });
    });
  }

  // View Vehicle History
  if (historyBtn) {
    historyBtn.addEventListener('click', () => {
      const req = AutoCareStore.getServiceRequestById(selectedRequestId);
      if (!req) return;
      const veh = AutoCareStore.data.vehicles.find(v => v.id === req.vehicle_id) || { model: 'Vehicle', registration_number: 'N/A' };

      openModal({
        title: `Service History: ${veh.model}`,
        content: `
          <div style="font-size: 13px;">
            <div style="margin-bottom: 12px; padding: 10px; background: #f8fafc; border-radius: 8px;">
              <strong>Plate:</strong> ${veh.registration_number} &nbsp;|&nbsp; <strong>Total Past Visits:</strong> 4
            </div>
            <div style="display: flex; flex-direction: column; gap: 10px; max-height: 250px; overflow-y: auto;">
              <div style="padding: 10px; border-left: 3px solid #10b981; background: #ffffff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                  <span>JC-1012 • 40k Periodic Maintenance</span>
                  <span style="color: #10b981;">Completed</span>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">Aug 14, 2023 • Engine oil changed, brake pads replaced. Total: ৳9,400</div>
              </div>
              <div style="padding: 10px; border-left: 3px solid #10b981; background: #ffffff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                  <span>JC-0985 • AC Compressor Servicing</span>
                  <span style="color: #10b981;">Completed</span>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">May 02, 2023 • Refrigerant refill and cabin filter. Total: ৳4,200</div>
              </div>
            </div>
          </div>
        `,
        confirmText: 'Close',
        cancelText: '',
        onConfirm: () => {}
      });
    });
  }
}

/**
 * 4. Action Controls: Filter, Sort, Export
 */
function initTopActionControls() {
  const buttons = document.querySelectorAll('.page-heading .action-buttons .btn-outline');
  if (buttons.length < 3) return;

  const [filterBtn, sortBtn, exportBtn] = buttons;

  // Filter Button
  filterBtn.addEventListener('click', () => {
    openModal({
      title: 'Filter Booking Requests',
      content: `
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Priority Level</label>
          <select id="modal-filter-priority" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            <option value="all">All Priorities</option>
            <option value="high">High Priority Only</option>
            <option value="normal">Normal Priority</option>
          </select>
        </div>
      `,
      confirmText: 'Apply Filter',
      onConfirm: () => {
        const val = document.getElementById('modal-filter-priority').value;
        renderBookingRequestsTable(val);
        showToast(`Filtered by priority: ${val}`, 'info');
      }
    });
  });

  // Sort Button
  let currentSort = 'date';
  sortBtn.addEventListener('click', () => {
    currentSort = currentSort === 'date' ? 'priority' : 'date';
    renderBookingRequestsTable('all', currentSort);
    showToast(`Sorted by: ${currentSort.toUpperCase()}`, 'info');
  });

  // Export Button (Downloads CSV)
  exportBtn.addEventListener('click', () => {
    const reqs = AutoCareStore.getServiceRequests();
    let csv = 'Booking ID,Customer,Vehicle,Service Date,Priority,Status\n';
    reqs.forEach(r => {
      const owner = AutoCareStore.getOwners().find(o => o.id === r.owner_id);
      const veh = AutoCareStore.data.vehicles.find(v => v.id === r.vehicle_id);
      csv += `"${r.code}","${owner ? owner.name : ''}","${veh ? veh.model : ''}","${r.requested_date}","${r.priority}","${r.status}"\n`;
    });

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `booking_requests_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    showToast('Booking Requests exported to CSV!', 'success');
  });
}
