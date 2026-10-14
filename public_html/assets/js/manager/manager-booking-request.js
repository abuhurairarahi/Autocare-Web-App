/**
 * manager-booking-request.js
 * Functionality for the Service Booking Requests page:
 * Row selection, right details panel updates, approving & creating job cards,
 * rejection, history view, filter, sort, and export.
 */

let selectedRequestId = null;
let bookingRequests = [];
let mechanicsCache = [];

document.addEventListener('DOMContentLoaded', () => {
  fetchBookingRequests();
  fetchMechanics();
  initTopActionControls();
});

async function fetchBookingRequests(filterPriority = 'all', sortBy = 'date') {
  try {
    const res = await fetch(`../../api/manager-api/manager-booking-request-api.php?action=list&priority=${filterPriority}&sort=${sortBy}&_t=${new Date().getTime()}`);
    const data = await res.json();
    if (data.success) {
      bookingRequests = data.requests || [];
    } else {
      throw new Error(data.error || 'API returned false');
    }
  } catch (err) {
    console.error('Error fetching booking requests. Using mock data fallback.', err);
    bookingRequests = [
      {
        id: 1, code: 'BRQ-2023-145', description: 'Driver reports harsh shifting between 2nd and 3rd gear. Occasional slipping when under heavy load. Check transmission fluid levels and perform diagnostic.',
        customer_name: 'Apex Logistics', phone: '(555) 123-4567', vehicle_make: 'Ford', vehicle_model: 'Transit 350', vehicle_year: 2018, vehicle_vin: '1FTYR2XG2JKA*****', vehicle_plate: 'LND-294-K', priority: 'High', status: 'Pending', created_at: 'Oct 23, 16:45'
      },
      {
        id: 2, code: 'BRQ-2023-146', description: 'Standard 50k Service. Replace engine oil and filters.',
        customer_name: 'Sarah Jenkins', phone: '(555) 987-6543', vehicle_make: 'Toyota', vehicle_model: 'RAV4', vehicle_year: 2020, vehicle_vin: 'JTMDFREV4583*****', vehicle_plate: 'NYC-882-M', priority: 'Normal', status: 'Pending', created_at: 'Oct 24, 09:30'
      }
    ];
  }

  if (bookingRequests.length > 0 && (!selectedRequestId || !bookingRequests.some(r => r.id === selectedRequestId))) {
    selectedRequestId = bookingRequests[0].id;
  } else if (bookingRequests.length === 0) {
    selectedRequestId = null;
  }
  renderBookingRequestsTable();
}

async function fetchMechanics() {
  try {
    const res = await fetch('../../api/manager-api/manager-booking-request-api.php?action=mechanics');
    const data = await res.json();
    if (data.success) {
      mechanicsCache = data.mechanics || [];
    } else {
      throw new Error();
    }
  } catch (err) {
    mechanicsCache = [
      { id: 1, name: 'J. Smith' },
      { id: 2, name: 'A. Davis' }
    ];
  }
}

function renderBookingRequestsTable() {
  const tableBody = document.querySelector('.table-panel table tbody');
  const countBadge = document.querySelector('.table-panel .badge-count');
  if (!tableBody) return;

  if (countBadge) {
    countBadge.innerText = `${bookingRequests.length} New`;
  }

  if (bookingRequests.length === 0) {
    tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No pending booking requests</td></tr>`;
    clearDetailsPanel();
    return;
  }

  let html = '';
  bookingRequests.forEach(r => {
    const isSelected = r.id === selectedRequestId;
    const priorityClass = r.priority.toLowerCase() === 'high' ? 'high' : 'normal';

    html += `
      <tr class="${isSelected ? 'selected' : ''}" data-request-id="${r.id}" style="cursor: pointer;">
        <td class="bold">${r.code}</td>
        <td>${r.customer_name}</td>
        <td>
          <div class="vehicle-cell">
            <span>${r.vehicle_make} ${r.vehicle_model}</span>
            <small>${r.vehicle_plate}</small>
          </div>
        </td>
        <td>Maintenance/Repair</td>
        <td>${r.created_at}</td>
        <td><span class="badge-priority ${priorityClass}">${r.priority}</span></td>
        <td><span class="badge-status">Pending Review</span></td>
      </tr>
    `;
  });

  tableBody.innerHTML = html;

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

  updateDetailsPanel(selectedRequestId);
}

function updateDetailsPanel(requestId) {
  const panel = document.querySelector('.details-panel');
  if (!panel || !requestId) return;

  const req = bookingRequests.find(r => r.id === requestId);
  if (!req) return;

  const titleEl = panel.querySelector('.details-header h2');
  const dateEl = panel.querySelector('.details-header .submitted-date');
  if (titleEl) titleEl.innerText = req.code;
  if (dateEl) dateEl.innerText = `Submitted ${req.created_at}`;

  const avatarEl = panel.querySelector('.owner-details .avatar-box');
  const ownerNameEl = panel.querySelector('.owner-text h3');
  const phoneEl = panel.querySelector('.owner-text p');
  
  if (avatarEl) {
    const initials = req.customer_name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    avatarEl.innerText = initials || 'C';
  }
  if (ownerNameEl) ownerNameEl.innerText = req.customer_name;
  if (phoneEl) {
    phoneEl.innerHTML = `<svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg> ${req.phone}`;
  }

  const vehTitleEl = panel.querySelector('.vehicle-title h3');
  const plateEl = panel.querySelector('.vehicle-specs .spec-val');
  const vinEl = panel.querySelectorAll('.vehicle-specs .spec-val')[1];
  if (vehTitleEl) vehTitleEl.innerText = `${req.vehicle_year} ${req.vehicle_make} ${req.vehicle_model}`;
  if (plateEl) plateEl.innerText = req.vehicle_plate;
  if (vinEl) vinEl.innerText = req.vehicle_vin;

  const descEl = panel.querySelector('.service-description p');
  if (descEl) descEl.innerText = req.description;
  
  initDetailActionButtons(req);
}

function clearDetailsPanel() {
  const panel = document.querySelector('.details-panel');
  if (!panel) return;
  const titleEl = panel.querySelector('.details-header h2');
  if (titleEl) titleEl.innerText = 'No Request Selected';
  const descEl = panel.querySelector('.service-description p');
  if (descEl) descEl.innerText = 'Select a booking request from the table to view details.';
  
  const approveBtn = document.querySelector('.details-panel .btn-approve');
  if (approveBtn) approveBtn.replaceWith(approveBtn.cloneNode(true));
  const rejectBtn = document.querySelector('.details-panel .btn-danger');
  if (rejectBtn) rejectBtn.replaceWith(rejectBtn.cloneNode(true));
  const historyBtn = document.querySelector('.details-panel .btn-secondary');
  if (historyBtn) historyBtn.replaceWith(historyBtn.cloneNode(true));
}

function initDetailActionButtons(req) {
  let approveBtn = document.querySelector('.details-panel .btn-approve');
  let rejectBtn = document.querySelector('.details-panel .btn-danger');
  let historyBtn = document.querySelector('.details-panel .btn-secondary');

  if(approveBtn) {
      const newApprove = approveBtn.cloneNode(true);
      approveBtn.replaceWith(newApprove);
      approveBtn = newApprove;
  }
  if(rejectBtn) {
      const newReject = rejectBtn.cloneNode(true);
      rejectBtn.replaceWith(newReject);
      rejectBtn = newReject;
  }
  if(historyBtn) {
      const newHistory = historyBtn.cloneNode(true);
      historyBtn.replaceWith(newHistory);
      historyBtn = newHistory;
  }

  if (approveBtn) {
    approveBtn.addEventListener('click', () => {
      const mechOptions = mechanicsCache
        .map(m => `<option value="${m.id}">${m.name}</option>`)
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
        `,
        confirmText: 'Approve & Create Job Card',
        onConfirm: async () => {
          const mechId = document.getElementById('modal-approve-mechanic').value;
          
          try {
            const res = await fetch('../../api/manager-api/manager-booking-request-api.php?action=approve', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                request_id: req.id,
                mechanic_id: mechId,
                customer_name: req.customer_name,
                vehicle_details: `${req.vehicle_make} ${req.vehicle_model} - ${req.vehicle_plate}`,
                service_text: req.description
              })
            });
            const data = await res.json();
            if (data.success) {
              showToast(`Job Card ${data.code} created for ${req.code}!`, 'success');
              fetchBookingRequests();
            } else {
              showToast('Error approving request', 'error');
            }
          } catch(err) {
             showToast('Network error approving request', 'error');
          }
        }
      });
    });
  }

  if (rejectBtn) {
    rejectBtn.addEventListener('click', () => {
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
        onConfirm: async () => {
          const reason = document.getElementById('modal-reject-reason').value;
          try {
            const res = await fetch('../../api/manager-api/manager-booking-request-api.php?action=reject', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ request_id: req.id, reason: reason })
            });
            const data = await res.json();
            if(data.success) {
              showToast(`Request ${req.code} has been rejected.`, 'info');
              fetchBookingRequests();
            } else {
              showToast('Error rejecting request', 'error');
            }
          } catch(err) {
             showToast('Network error rejecting request', 'error');
          }
        }
      });
    });
  }

  if (historyBtn) {
    historyBtn.addEventListener('click', async () => {
      try {
        const res = await fetch(`../../api/manager-api/manager-booking-request-api.php?action=history&plate=${encodeURIComponent(req.vehicle_plate)}`);
        const data = await res.json();
        
        let historyHtml = '';
        if (data.history && data.history.length > 0) {
            historyHtml = data.history.map(h => `
              <div style="padding: 10px; border-left: 3px solid #10b981; background: #ffffff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                  <span>${h.code} • ${h.title}</span>
                  <span style="color: #10b981;">${h.status}</span>
                </div>
                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">${h.date} • ${h.details}</div>
              </div>
            `).join('');
        } else {
            historyHtml = '<p style="font-size: 13px; color: #64748b;">No service history found for this vehicle.</p>';
        }

        openModal({
          title: `Service History: ${req.vehicle_make} ${req.vehicle_model}`,
          content: `
            <div style="font-size: 13px;">
              <div style="margin-bottom: 12px; padding: 10px; background: #f8fafc; border-radius: 8px;">
                <strong>Plate:</strong> ${req.vehicle_plate} &nbsp;|&nbsp; <strong>Total Past Visits:</strong> ${data.history ? data.history.length : 0}
              </div>
              <div style="display: flex; flex-direction: column; max-height: 250px; overflow-y: auto;">
                ${historyHtml}
              </div>
            </div>
          `,
          confirmText: 'Close',
          cancelText: '',
          onConfirm: () => {}
        });
      } catch(err) {
         showToast('Network error fetching history', 'error');
      }
    });
  }
}

function initTopActionControls() {
  const buttons = document.querySelectorAll('.page-heading .action-buttons .btn-outline');
  if (buttons.length < 3) return;

  const [filterBtn, sortBtn, exportBtn] = buttons;

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
        fetchBookingRequests(val, 'date');
        showToast(`Filtered by priority: ${val}`, 'info');
      }
    });
  });

  let currentSort = 'date';
  sortBtn.addEventListener('click', () => {
    currentSort = currentSort === 'date' ? 'priority' : 'date';
    fetchBookingRequests('all', currentSort);
    showToast(`Sorted by: ${currentSort.toUpperCase()}`, 'info');
  });

  exportBtn.addEventListener('click', () => {
    let csv = 'Booking ID,Customer,Vehicle,Service Date,Priority,Status\\n';
    bookingRequests.forEach(r => {
      csv += `"${r.code}","${r.customer_name}","${r.vehicle_make} ${r.vehicle_model}","${r.created_at}","${r.priority}","${r.status}"\\n`;
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
