/**
 * manager-dashboard.js
 * Dynamic functionality for the AutoCare Manager Dashboard page.
 */

document.addEventListener('DOMContentLoaded', () => {
  fetchDashboardData();
  initNewJobCardButton();
  initRevenuePeriodToggle();

  // Background polling for real-time updates (every 10 seconds)
  setInterval(() => {
    fetchDashboardData(true);
  }, 10000);
});

let dashboardDataCache = null; // Store fetched data

async function fetchDashboardData(silent = false) {
  try {
    const res = await fetch('../../api/manager-api/manager-dashboard-api.php?action=dashboard');
    const data = await res.json();
    if (data.success) {
      dashboardDataCache = data;
      renderDashboardStats(data);
      renderMechanicsWorkload(data);
      renderRecentActivity(data);
      if (!silent) initViewAllActivities(data.activities);
    }
  } catch (err) {
    if (!silent) console.error('Error fetching dashboard data:', err);
  }
}

/**
 * 1. Calculate and update dashboard stat cards from the live database store
 */
function renderDashboardStats(data) {
  // Update DOM elements by ID
  const sPending = document.getElementById('stat-pending');
  if (sPending) sPending.innerText = data.stats.pending_bookings;

  const sActive = document.getElementById('stat-active');
  if (sActive) sActive.innerText = data.stats.active_jobs;

  const sMech = document.getElementById('stat-mechanics');
  if (sMech) sMech.innerHTML = `${data.stats.mechanics_assigned}<span class="total">/${data.stats.mechanics_total}</span>`;

  const sWait = document.getElementById('stat-waiting');
  if (sWait) sWait.innerText = data.stats.waiting_approval;

  const sRev = document.getElementById('stat-revenue');
  if (sRev) sRev.innerText = `৳${(data.stats.monthly_revenue / 1000).toFixed(1)}k`;

  // Make all cards clickable to navigate to their corresponding manager page
  const statCards = document.querySelectorAll('.stats .stat-card');
  const cardDestinations = [
    'manager-booking-request.html',
    'manager-jobCards.html',
    'manager-mechanics.html',
    'manager-payment-approval.html',
    'manager-invoice-management.html'
  ];

  if (statCards.length > 0) {
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
    const mechanics = dashboardDataCache ? (dashboardDataCache.mechanics || []) : [];
    const mechOptions = mechanics
      .map(m => `<option value="${m.id}">${m.name} (${m.specialty} - ${m.status})</option>`)
      .join('');

    const pendingReqs = dashboardDataCache ? (dashboardDataCache.pending_requests || []) : [];
    const reqOptions = pendingReqs
      .map(r => `<option value="${r.id}">${r.code || 'Req #'+r.id} - ${(r.description || '').substring(0, 35)}...</option>`)
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
      cancelText: 'Cancel',
      onConfirm: async () => {
        const custName = document.getElementById('modal-cust-name').value.trim();
        const vehicle = document.getElementById('modal-vehicle-model').value.trim();
        const mechId = document.getElementById('modal-mechanic-select').value;
        const estCost = parseFloat(document.getElementById('modal-est-cost').value) || 0;
        const desc = document.getElementById('modal-service-desc').value.trim();

        if (!custName || !vehicle) {
          showToast('Please enter both Customer Name and Vehicle!', 'warning');
          return;
        }

        try {
          const res = await fetch('../../api/manager-api/manager-dashboard-api.php?action=create_job_card', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              customer_name: custName,
              vehicle_details: vehicle,
              mechanic_id: mechId,
              estimated_cost: estCost,
              service_text: desc
            })
          });
          const result = await res.json();
          if (result.success) {
            showToast(`Job Card ${result.code} created successfully!`, 'success');
            fetchDashboardData(); // Refresh all live data
          } else {
            showToast('Failed to create Job Card', 'error');
          }
        } catch (err) {
          showToast('Network error', 'error');
        }
      }
    });

    // Populate fields if request is selected
    setTimeout(() => {
      const reqSelect = document.getElementById('modal-req-select');
      if (reqSelect) {
        reqSelect.addEventListener('change', (e) => {
          const req = pendingReqs.find(r => String(r.id) === String(e.target.value));
          if (req) {
            if (req.customer_name) document.getElementById('modal-cust-name').value = req.customer_name;
            if (req.vehicle_details) document.getElementById('modal-vehicle-model').value = req.vehicle_details;
            if (req.description) document.getElementById('modal-service-desc').value = req.description;
          } else {
            document.getElementById('modal-cust-name').value = '';
            document.getElementById('modal-vehicle-model').value = '';
            document.getElementById('modal-service-desc').value = '';
          }
        });
      }
    }, 100);
  });
}

let revenueChartInstance = null;

function initRevenuePeriodToggle() {
  const periodBtns = document.querySelectorAll('.revenue-panel .period button');
  const canvas = document.getElementById('revenueChart');

  if (!periodBtns.length || !canvas) return;

  const selectedBtn = document.querySelector('.revenue-panel .period button.selected');
  const initialPeriod = selectedBtn ? selectedBtn.innerText.toLowerCase() : 'monthly';
  fetchTrendData(initialPeriod);

  periodBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      periodBtns.forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');

      const period = btn.innerText.toLowerCase();
      // Optional: showToast(`Showing ${period.charAt(0).toUpperCase() + period.slice(1)} Revenue Trend`, 'info', 1000);
      fetchTrendData(period);
    });
  });
}

async function fetchTrendData(period) {
  try {
    const res = await fetch(`../../api/manager-api/manager-dashboard-api.php?action=trend&period=${period}`);
    const data = await res.json();
    if (data.success) {
      renderChart(data.labels, data.points, period);
    }
  } catch (err) {
    console.error('Error fetching trend data:', err);
  }
}

function renderChart(labels, dataPoints, period) {
  const ctx = document.getElementById('revenueChart').getContext('2d');
  
  if (revenueChartInstance) {
    revenueChartInstance.destroy();
  }
  
  revenueChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: period === 'monthly' ? 'Daily Revenue' : 'Monthly Revenue',
        data: dataPoints,
        borderColor: '#3b82f6',
        backgroundColor: 'rgba(59, 130, 246, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 3,
        pointHoverRadius: 6
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label: function(context) {
              return '৳ ' + context.parsed.y.toLocaleString();
            }
          }
        }
      },
      scales: {
        y: {
          beginAtZero: true,
          grid: { color: '#e2e8f0', drawBorder: false },
          ticks: {
            callback: function(value) {
              if (value >= 1000) return '৳' + (value/1000) + 'k';
              return '৳' + value;
            }
          }
        },
        x: {
          grid: { display: false, drawBorder: false }
        }
      }
    }
  });
}

/**
 * 4. Render Mechanics Workload list dynamically from API
 */
function renderMechanicsWorkload(data) {
  const workloadBody = document.querySelector('.workload .workload-body');
  if (!workloadBody || !data.mechanics) return;

  let html = '';
  data.mechanics.slice(0, 5).forEach(m => {
    const isDanger = m.workload_pct >= 90;

    html += `
      <div class="mechanic" style="cursor: pointer;" title="Click to view mechanic" onclick="window.location.href='manager-mechanics.html'">
        <div><span>${m.name}</span><b>${m.jobs} ${m.jobs === 1 ? 'Job' : 'Jobs'}</b></div>
        <div class="bar"><i class="${isDanger ? 'danger' : ''}" style="width: ${m.workload_pct}%;"></i></div>
      </div>
    `;
  });

  workloadBody.innerHTML = html;
}

/**
 * 5. Render Recent Activities from API
 */
function renderRecentActivity(data) {
  const activitySection = document.querySelector('section.activity');
  if (!activitySection || !data.activities) return;

  const existingRows = activitySection.querySelectorAll('.activity-row');
  existingRows.forEach(r => r.remove());

  data.activities.slice(0, 4).forEach(act => {
    const row = document.createElement('div');
    row.className = 'activity-row';
    row.style.cursor = 'pointer';
    row.onclick = () => { window.location.href = act.link || '#'; };

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
        <small>${act.subtext || ''}</small>
      </div>
    `;

    activitySection.appendChild(row);
  });
}

/**
 * 6. View All Activities Popup Modal
 */
function initViewAllActivities(activities) {
  const viewAllBtn = document.getElementById('view-all-activities');
  if (!viewAllBtn) return;
  
  // Clone to remove previous event listeners
  const newBtn = viewAllBtn.cloneNode(true);
  viewAllBtn.parentNode.replaceChild(newBtn, viewAllBtn);
  
  newBtn.addEventListener('click', (e) => {
    e.preventDefault();
    
    let fullContent = '<div style="max-height: 400px; overflow-y: auto; padding-right: 8px;">';
    if (!activities || activities.length === 0) {
      fullContent += '<p style="color:#64748b; font-size:13px;">No recent activities.</p>';
    } else {
      activities.forEach(a => {
        let borderClass = '3b82f6';
        if (a.type === 'red') borderClass = 'ef4444';
        if (a.type === 'gray') borderClass = '94a3b8';
        fullContent += `
          <div style="padding: 12px 16px; border: 1px solid #e2e8f0; border-left: 4px solid #${borderClass}; background: #f8fafc; border-radius: 6px; margin-bottom: 8px; cursor: pointer;" onclick="window.location.href='${a.link || '#'}'">
            <div style="font-size: 13px; color: #334155; font-weight: 500;">${a.text}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 6px;">${a.subtext || ''}</div>
          </div>
        `;
      });
    }
    fullContent += '</div>';
    
    openModal({
      title: 'All Recent Activities',
      content: fullContent,
      confirmText: 'Done',
      cancelText: 'Close'
    });
  });
}
