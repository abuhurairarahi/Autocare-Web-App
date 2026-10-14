/**
 * manager-dashboard.js
 * Dynamic functionality for the AutoCare Manager Dashboard page.
 */

document.addEventListener('DOMContentLoaded', () => {
  renderDashboardStats();
  initNewJobCardButton();
  initRevenuePeriodToggle();
  renderMechanicsWorkload();
  renderRecentActivity();
});

window.addEventListener('autocare:store:synced', () => {
  renderDashboardStats();
  renderMechanicsWorkload();
  renderRecentActivity();
});

/**
 * 1. Calculate and update dashboard stat cards from the live database store
 */
function renderDashboardStats() {
  if (typeof AutoCareStore === 'undefined') return;

  const requests = AutoCareStore.getServiceRequests();
  const jobCards = AutoCareStore.getJobCards();
  const mechanics = AutoCareStore.getMechanics();
  const partsPending = AutoCareStore.getJobCardParts().filter(p => p.status === 'Pending Approval');
  const estimatesPending = AutoCareStore.getEstimates().filter(e => e.status !== 'Approved');
  const invoices = AutoCareStore.getInvoices();

  // Pending Bookings
  const pendingRequestsCount = requests.filter(r => r.status === 'Pending').length;
  // Active Job Cards
  const activeJobCardsCount = jobCards.filter(j => j.status !== 'Delivered' && j.kanban_stage !== 'COMPLETED').length;
  // Assigned Mechanics
  const busyMechanicsCount = mechanics.filter(m => m.workload > 0 || m.status === 'Busy').length;
  const totalMechanics = mechanics.length;
  // Waiting Approval
  const waitingApprovalCount = partsPending.length + estimatesPending.length;
  // Monthly Revenue (sum of paid invoices)
  const totalRevenue = invoices
    .filter(i => i.status === 'Paid')
    .reduce((sum, inv) => sum + (inv.total_amount || 0), 0);

  // Update DOM elements if present
  // Update DOM elements and wire navigation links
  const statCards = document.querySelectorAll('.stats .stat-card');
  const cardDestinations = [
    'manager-booking-request.html',
    'manager-jobCards.html',
    'manager-mechanics.html',
    'manager-payment-approval.html',
    'manager-invoice-management.html'
  ];

  if (statCards.length >= 5) {
    // Card 1: Pending Bookings
    const val1 = statCards[0].querySelector('strong');
    if (val1) val1.innerText = pendingRequestsCount;

    // Card 2: Active Job Cards
    const val2 = statCards[1].querySelector('strong');
    if (val2) val2.innerText = activeJobCardsCount;

    // Card 3: Assigned Mechanics
    const val3 = statCards[2].querySelector('strong');
    if (val3) val3.innerHTML = `${busyMechanicsCount}<span class="total">/${totalMechanics}</span>`;

    // Card 4: Waiting Approval
    const val4 = statCards[3].querySelector('strong');
    if (val4) val4.innerText = waitingApprovalCount;

    // Card 5: Monthly Revenue
    const val5 = statCards[4].querySelector('strong');
    if (val5) val5.innerText = `৳${(totalRevenue / 1000).toFixed(1)}k`;

    // Make all cards clickable to navigate to their corresponding manager page
    statCards.forEach((card, index) => {
      card.style.cursor = 'pointer';
      if (!card.hasAttribute('data-nav-wired')) {
        card.setAttribute('data-nav-wired', 'true');
        card.addEventListener('click', () => {
          const dest = cardDestinations[index];
          if (dest) window.location.href = dest;
        });
      }
    });
  }
}

/**
 * 2. "+ New Job Card" button handler
 */
function initNewJobCardButton() {
  const newJobBtn = document.querySelector('.page-heading .new-job');
  if (!newJobBtn) return;

  newJobBtn.addEventListener('click', () => {
    if (typeof AutoCareStore === 'undefined') return;

    const mechanics = AutoCareStore.getMechanics();
    const pendingReqs = AutoCareStore.getServiceRequests().filter(r => r.status === 'Pending');

    const mechOptions = mechanics
      .map(m => `<option value="${m.id}">${m.name} (${m.specialty} - ${m.status})</option>`)
      .join('');

    const reqOptions = pendingReqs
      .map(r => `<option value="${r.id}">${r.code} - ${r.description.substring(0, 35)}...</option>`)
      .join('');

    const formContent = `
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Link from Pending Request (Optional)</label>
          <select id="modal-req-select" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            <option value="">-- Manual Entry --</option>
            ${reqOptions}
          </select>
        </div>

        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Customer Name *</label>
          <input type="text" id="modal-cust-name" placeholder="e.g. Rahim Uddin" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
        </div>

        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Vehicle & Model *</label>
          <input type="text" id="modal-vehicle-model" placeholder="e.g. 2021 Toyota Corolla (Dhaka-Metro-Ga-12-3456)" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Assign Mechanic</label>
            <select id="modal-mechanic-select" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              <option value="">-- Assign Later --</option>
              ${mechOptions}
            </select>
          </div>
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Est. Cost (৳)</label>
            <input type="number" id="modal-est-cost" value="5000" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
          </div>
        </div>

        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Service Scope / Description</label>
          <textarea id="modal-service-desc" rows="2" placeholder="Details of repair requested..." style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;"></textarea>
        </div>
      </div>
    `;

    openModal({
      title: 'Create New Job Card',
      content: formContent,
      confirmText: 'Create Job Card',
      onConfirm: () => {
        const custName = document.getElementById('modal-cust-name').value.trim();
        const vehicle = document.getElementById('modal-vehicle-model').value.trim();
        const mechId = document.getElementById('modal-mechanic-select').value;
        const estCost = parseFloat(document.getElementById('modal-est-cost').value) || 0;
        const desc = document.getElementById('modal-service-desc').value.trim();
        const reqId = document.getElementById('modal-req-select').value;

        if (!custName || !vehicle) {
          showToast('Please enter both Customer Name and Vehicle!', 'warning');
          return;
        }

        const selectedMech = mechId ? AutoCareStore.getMechanicById(mechId) : null;

        const newJob = AutoCareStore.createJobCard({
          customer_name: custName,
          vehicle_details: vehicle,
          vehicle_title: vehicle.split('(')[0].trim(),
          service_text: desc || 'General Diagnosis & Repair',
          estimated_cost: estCost,
          mechanic_id: selectedMech ? selectedMech.id : null,
          mechanic_name: selectedMech ? selectedMech.name : 'Unassigned',
          mechanic_initials: selectedMech ? selectedMech.name.split(' ').map(n=>n[0]).join('') : 'UA',
          status: 'Diagnosis',
          kanban_stage: 'PENDING'
        });

        if (reqId) {
          AutoCareStore.updateServiceRequestStatus(reqId, 'Approved');
        }

        if (selectedMech) {
          AutoCareStore.updateMechanicWorkload(selectedMech.id, (selectedMech.workload || 0) + 20, 'Busy');
        }

        showToast(`Job Card ${newJob.code} created successfully!`, 'success');
        renderDashboardStats();
        renderMechanicsWorkload();
        renderRecentActivity();
      }
    });

    // Populate fields if request is selected
    setTimeout(() => {
      const reqSelect = document.getElementById('modal-req-select');
      if (reqSelect) {
        reqSelect.addEventListener('change', (e) => {
          const req = pendingReqs.find(r => r.id === Number(e.target.value));
          if (req) {
            const owner = AutoCareStore.getOwners().find(o => o.id === req.owner_id);
            const veh = AutoCareStore.data.vehicles.find(v => v.id === req.vehicle_id);
            if (owner) document.getElementById('modal-cust-name').value = owner.name;
            if (veh) document.getElementById('modal-vehicle-model').value = `${veh.model} (${veh.registration_number})`;
            document.getElementById('modal-service-desc').value = req.description;
          }
        });
      }
    }, 100);
  });
}

/**
 * 3. Revenue Trend Chart toggle between Weekly and Monthly
 */
function initRevenuePeriodToggle() {
  const periodBtns = document.querySelectorAll('.revenue-panel .period button');
  const lineSvg = document.querySelector('.chart-line-svg polyline');
  const monthsContainer = document.querySelector('.chart .months');

  if (!periodBtns.length || !lineSvg || !monthsContainer) return;

  const monthlyPoints = '0,166 90,120 180,136 276,74 366,96 462,34 600,54';
  const monthlyLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];

  const weeklyPoints = '0,150 100,105 200,120 300,60 400,80 500,25 600,40';
  const weeklyLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

  periodBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      periodBtns.forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');

      const isWeekly = btn.innerText.toLowerCase().includes('week');

      lineSvg.style.transition = 'all 0.5s ease';
      lineSvg.setAttribute('points', isWeekly ? weeklyPoints : monthlyPoints);

      const labels = isWeekly ? weeklyLabels : monthlyLabels;
      monthsContainer.innerHTML = labels.map(l => `<span>${l}</span>`).join('');

      showToast(`Showing ${isWeekly ? 'Weekly' : 'Monthly'} Revenue Trend`, 'info');
    });
  });
}

/**
 * 4. Render Mechanics Workload list dynamically from store
 */
function renderMechanicsWorkload() {
  const workloadBody = document.querySelector('.workload .workload-body');
  if (!workloadBody || typeof AutoCareStore === 'undefined') return;

  const mechanics = AutoCareStore.getMechanics();
  const jobCards = AutoCareStore.getJobCards();

  let html = '';
  mechanics.slice(0, 5).forEach(m => {
    const activeJobs = jobCards.filter(j => j.mechanic_id === m.id && j.kanban_stage !== 'COMPLETED').length;
    const workloadPct = Math.min(100, activeJobs * 20 || m.workload || 10);
    const isDanger = workloadPct >= 90;

    html += `
      <div class="mechanic" style="cursor: pointer;" title="Click to view mechanic" onclick="window.location.href='manager-mechanics.html'">
        <div><span>${m.name}</span><b>${activeJobs} ${activeJobs === 1 ? 'Job' : 'Jobs'}</b></div>
        <div class="bar"><i class="${isDanger ? 'danger' : ''}" style="width: ${workloadPct}%;"></i></div>
      </div>
    `;
  });

  workloadBody.innerHTML = html;
}

/**
 * 5. Render Recent Activities from store
 */
function renderRecentActivity() {
  const activitySection = document.querySelector('section.activity');
  if (!activitySection || typeof AutoCareStore === 'undefined') return;

  const activities = AutoCareStore.getRecentActivity();
  const existingRows = activitySection.querySelectorAll('.activity-row');
  existingRows.forEach(r => r.remove());

  activities.slice(0, 4).forEach(act => {
    const row = document.createElement('div');
    row.className = 'activity-row';

    let circleClass = 'blue-circle';
    if (act.type === 'red') circleClass = 'red-circle';
    else if (act.type === 'gray') circleClass = 'gray-circle';

    row.innerHTML = `
      <div class="activity-icon ${circleClass}">
        <svg viewBox="0 0 24 24" style="width: 18px; height: 18px;">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
          <polyline points="14 2 14 8 20 8"></polyline>
          <line x1="16" y1="13" x2="8" y2="13"></line>
        </svg>
      </div>
      <div class="activity-text">
        <div>${act.text}</div>
        <small>${act.time} ${act.subtext ? '<span>•</span> <em>' + act.subtext + '</em>' : ''}</small>
      </div>
    `;

    activitySection.appendChild(row);
  });
}
