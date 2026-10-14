/**
 * manager-performance.js
 * Logic for Performance Analytics:
 * Dynamic KPI calculation, period filtering, interactive bar chart with hover tooltips,
 * and analytics CSV export.
 * Now Fully Synced to MySQL Database!
 */

let currentPeriod = '30';
let currentMetrics = null;

document.addEventListener('DOMContentLoaded', () => {
  fetchPerformanceData(currentPeriod);
  initPerformanceFilters();
  initExportAnalytics();

  setInterval(() => {
    fetchPerformanceData(currentPeriod, true);
  }, 5000);
});

async function fetchPerformanceData(period, isBackground = false) {
  try {
    const res = await fetch(`../../api/manager-api/manager-performance-api.php?action=metrics&period=${period}&_t=${new Date().getTime()}`);
    const data = await res.json();
    if (data.success && data.metrics) {
      currentMetrics = data.metrics;
      renderPerformanceKpis(period, data.metrics);
      renderTopMechanicsTable(data.metrics.top_mechanics);
      if (data.metrics.chart_data) {
        renderCharts(data.metrics.chart_data);
      }
    }
  } catch(err) {
    if (!isBackground) console.error('API Error', err);
  }
}

/**
 * 1. Calculate & Render KPI Cards
 */
function renderPerformanceKpis(period, metrics) {
  let periodLabel = 'vs last 30 days';
  if (period === '7') periodLabel = 'vs last 7 days';
  else if (period === 'year') periodLabel = 'vs last year';

  const kpiCards = document.querySelectorAll('.kpi-grid .kpi-card');
  if (kpiCards.length >= 4) {
    // 1. Total Revenue
    kpiCards[0].querySelector('.kpi-value').innerText = `৳${(metrics.total_revenue / 1000).toFixed(1)}k`;
    kpiCards[0].querySelector('.kpi-trend span').innerText = periodLabel;

    // 2. Completed Jobs
    kpiCards[1].querySelector('.kpi-value').innerText = metrics.completed_jobs;
    kpiCards[1].querySelector('.kpi-trend span').innerText = periodLabel;

    // 3. Avg Repair Time
    kpiCards[2].querySelector('.kpi-value').innerHTML = `${metrics.avg_repair_time} <span class="unit" style="font-size: 14px; font-weight: 500;">hrs</span>`;
    kpiCards[2].querySelector('.kpi-trend span').innerText = periodLabel;

    // 4. Parts Cost
    kpiCards[3].querySelector('.kpi-value').innerText = `৳${(metrics.parts_cost / 1000).toFixed(1)}k`;
    kpiCards[3].querySelector('.kpi-trend span').innerText = periodLabel;

    // Attach click navigation to cards
    const kpiDestinations = [
      'manager-invoice-management.html',
      'manager-jobCards.html',
      'manager-process-tracker.html',
      'manager-payment-approval.html'
    ];
    kpiCards.forEach((card, idx) => {
      card.style.cursor = 'pointer';
      if (!card.hasAttribute('data-nav-wired')) {
        card.setAttribute('data-nav-wired', 'true');
        card.addEventListener('click', () => {
          if (kpiDestinations[idx]) window.location.href = kpiDestinations[idx];
        });
      }
    });
  }
}

/**
 * Render Top Mechanics Table Dynamically
 */
function renderTopMechanicsTable(mechanics) {
  const tbody = document.querySelector('.table-card tbody');
  if (!tbody) return;

  if (!mechanics || mechanics.length === 0) {
    tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:20px; color:#64748b;">No completed jobs found for this period.</td></tr>`;
    return;
  }

  let html = '';
  mechanics.forEach(m => {
    html += `
      <tr style="cursor: pointer;" onclick="window.location.href='manager-mechanics.html';">
        <td>
          <div class="mechanic-cell">
            <div class="avatar-circle navy">${m.initials}</div>
            <div>
              <div class="mechanic-name">${m.name}</div>
              <div class="mechanic-role">${m.role}</div>
            </div>
          </div>
        </td>
        <td>${m.jobs}</td>
        <td>${m.avg_time} hrs</td>
        <td><span class="badge-pill blue">${m.efficiency}%</span></td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

/**
 * 2. Filter Controls (Period & Category)
 */
function initPerformanceFilters() {
  const selects = document.querySelectorAll('.page-header .select-dropdown');
  if (selects.length < 2) return;

  const [periodSelect, categorySelect] = selects;

  periodSelect.addEventListener('change', (e) => {
    const val = e.target.value.toLowerCase();
    let periodKey = '30';
    if (val.includes('7')) periodKey = '7';
    else if (val.includes('year')) periodKey = 'year';

    currentPeriod = periodKey;
    fetchPerformanceData(currentPeriod);
    showToast(`Performance range set to: ${e.target.value}`, 'info');
  });

  categorySelect.addEventListener('change', (e) => {
    showToast(`Filtered category: ${e.target.value}`, 'info');
  });
}

/**
 * 3. Render Charts (Bar & Donut)
 */
function renderCharts(chartData) {
  // Update Donut Chart
  const onTimePct = chartData.completion_rate.on_time_pct;
  const delayedPct = chartData.completion_rate.delayed_pct;
  
  const donutRing = document.querySelector('.donut-ring');
  const donutCenterText = document.querySelector('.donut-center .percentage');
  const legendOnTime = document.querySelectorAll('.chart-legend .legend-value')[0];
  const legendDelayed = document.querySelectorAll('.chart-legend .legend-value')[1];

  if (donutRing && donutCenterText) {
    donutCenterText.innerText = `${onTimePct}%`;
    donutRing.style.background = `conic-gradient(#2563eb ${onTimePct}%, #e2e8f0 ${onTimePct}%)`;
    if (legendOnTime) legendOnTime.innerText = `${onTimePct}%`;
    if (legendDelayed) legendDelayed.innerText = `${delayedPct}%`;
  }

  // Bar Chart Data Prep
  if (!chartData.revenue_trend) return;
  const monthData = chartData.revenue_trend;
  
  const maxRev = Math.max(...Object.values(monthData).map(m => m.raw_rev));
  
  const bars = document.querySelectorAll('.bar-chart-container .bar-group .bar');
  if (!bars.length) return;

  // Create or get floating tooltip
  let tooltip = document.getElementById('chart-tooltip');
  if (!tooltip) {
    tooltip = document.createElement('div');
    tooltip.id = 'chart-tooltip';
    tooltip.style.cssText = `
      position: absolute;
      padding: 6px 12px;
      background: #0f172a;
      color: #ffffff;
      font-size: 12px;
      font-weight: 600;
      border-radius: 6px;
      pointer-events: none;
      display: none;
      z-index: 100;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    document.body.appendChild(tooltip);
  }

  bars.forEach(bar => {
    const parentGroup = bar.closest('.bar-group');
    const label = parentGroup ? parentGroup.querySelector('.bar-label').innerText.trim() : 'Month';
    const data = monthData[label];
    
    if (data) {
      // Calculate dynamic height based on max revenue
      let heightPct = 10;
      if (maxRev > 0) {
        heightPct = Math.max(10, Math.round((data.raw_rev / maxRev) * 100));
      }
      bar.style.height = heightPct + '%';
      
      // Remove old listeners to avoid duplicates
      const newBar = bar.cloneNode(true);
      bar.parentNode.replaceChild(newBar, bar);
      
      newBar.style.cursor = 'pointer';
      newBar.style.transition = 'opacity 0.2s, height 0.5s ease-out, transform 0.2s';

      newBar.addEventListener('mouseenter', (e) => {
        newBar.style.opacity = '0.75';
        newBar.style.transform = 'scaleY(1.03)';
        tooltip.innerHTML = `<strong>${label}</strong>: ${data.rev} (${data.jobs} jobs)`;
        tooltip.style.display = 'block';
      });

      newBar.addEventListener('mousemove', (e) => {
        tooltip.style.left = (e.pageX + 10) + 'px';
        tooltip.style.top = (e.pageY - 35) + 'px';
      });

      newBar.addEventListener('mouseleave', () => {
        newBar.style.opacity = '1';
        newBar.style.transform = 'none';
        tooltip.style.display = 'none';
      });

      newBar.addEventListener('click', () => {
        document.querySelectorAll('.bar-chart-container .bar-group .bar').forEach(b => b.classList.remove('active'));
        newBar.classList.add('active');
        showToast(`Selected ${label}: Revenue ${data.rev}`, 'success');
      });
    }
  });
}


/**
 * 4. Export CSV Report
 */
function initExportAnalytics() {
  const exportBtn = document.querySelector('.page-header .btn-export');
  if (!exportBtn) return;

  exportBtn.addEventListener('click', (e) => {
    e.preventDefault();
    if (!currentMetrics) {
      showToast('Data is still loading or unavailable. Please wait.', 'warning');
      return;
    }

    const rows = [
      ['Metric', 'Value', 'Period'],
      ['Total Revenue', `৳${currentMetrics.total_revenue}`, currentPeriod],
      ['Completed Jobs', currentMetrics.completed_jobs, currentPeriod],
      ['Avg Repair Time', `${currentMetrics.avg_repair_time} hrs`, currentPeriod],
      ['Parts Cost', `৳${currentMetrics.parts_cost}`, currentPeriod],
      ['', '', ''],
      ['Top Mechanics', 'Jobs Completed', 'Efficiency']
    ];

    if (currentMetrics.top_mechanics) {
      currentMetrics.top_mechanics.forEach(m => {
        rows.push([m.name, m.jobs, `${m.efficiency}%`]);
      });
    }

    let csv = rows.map(e => e.join(',')).join('\n');
    // Add BOM for Excel compatibility with ৳ symbol
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `performance_analytics_${currentPeriod}_${new Date().toISOString().split('T')[0]}.csv`;
    
    document.body.appendChild(a);
    a.click();
    setTimeout(() => {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 100);
    
    showToast('Analytics summary exported to CSV!', 'success');
  });
}
