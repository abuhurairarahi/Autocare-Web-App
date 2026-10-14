/**
 * dashboard.js
 * Specific interactions and dynamic functionality for the dashboard page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("dashboard page loaded successfully.");
    initSpecificInteractions();
    loadDashboardStats();
});

async function loadDashboardStats() {
    if (!window.adminApi) return;
    const stats = await window.adminApi.request('dashboard.php', 'GET');
    if (stats) {
        const statValues = document.querySelectorAll('.stat-value');
        if (statValues.length >= 4) {
            statValues[0].innerText = stats.total_managers || 0;
            statValues[1].innerText = stats.active_mechanics || 0;
            statValues[2].innerText = stats.active_workshops || 0;
            statValues[3].innerText = '৳' + (stats.total_revenue ? stats.total_revenue.toLocaleString('en-IN') : '0');
        }
        generateRevenueTrend(stats.revenue_trend);
        generateServicesByCategory(stats.services_by_category);
        generateTopWorkshops(stats.top_workshops);
        generateServiceDistribution(stats.services_by_category);
        generateRecentActivity(stats.recent_activity);
    }
}

function initSpecificInteractions() {
    // 2. Wire up specific page buttons
    const primaryBtns = document.querySelectorAll('.primary-btn, .btn-primary');
    primaryBtns.forEach(btn => {
        // Only attach if it doesn't already have an action from global admin.js
        if(!btn.hasAttribute('data-wired')) {
            btn.setAttribute('data-wired', 'true');
            btn.addEventListener('click', (e) => {
                const actionText = e.target.innerText.trim();
                // We use the global showToast if available, otherwise alert
                if (typeof showToast === 'function') {
                    showToast(actionText + " action triggered successfully!");
                } else {
                    alert(actionText + " action triggered successfully!");
                }
            });
        }
    });

    // 4. Handle specific filters
    const selects = document.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', (e) => {
            if (typeof showToast === 'function') {
                showToast("Data filtered by: " + e.target.options[e.target.selectedIndex].text);
            }
        });
    });
}

function initVisualizations() {
    // Replaced by loadDashboardStats
}

function generateRevenueTrend(trendData) {
    const ctx = document.getElementById('revenueChart');
    if (!ctx || !trendData) return;

    const data = {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
            label: 'Revenue (৳)',
            data: trendData,
            borderColor: '#f97316',
            backgroundColor: 'rgba(249, 115, 22, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#ffffff',
            pointBorderColor: '#f97316',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += '৳' + context.parsed.y.toLocaleString('en-IN');
                            }
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '৳' + (value / 1000) + 'k';
                        }
                    },
                    grid: {
                        color: '#f1f5f9'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    };

    new Chart(ctx, config);
}

function generateServicesByCategory(catData) {
    const container = document.getElementById('servicesCategoryChart');
    if (!container || !catData || !catData.labels) return;

    const colors = ['#3D4F91', '#6E7FBE', '#8C9AD3', '#A9B5E5', '#c4c4c4'];
    let categories = [];
    
    // Normalize data to percentages (assuming max height 100%)
    let maxVal = Math.max(...catData.data, 1);
    catData.labels.forEach((label, idx) => {
        categories.push({
            label: label,
            height: Math.round((catData.data[idx] / maxVal) * 100),
            color: colors[idx % colors.length]
        });
    });

    container.innerHTML = '';
    categories.forEach(cat => {
        const barWrap = document.createElement('div');
        barWrap.style.display = 'flex';
        barWrap.style.flexDirection = 'column';
        barWrap.style.alignItems = 'center';
        barWrap.style.height = '100%';
        barWrap.style.justifyContent = 'flex-end';
        barWrap.style.flex = '1';
        barWrap.style.gap = '4px';

        const bar = document.createElement('div');
        bar.className = 'bar';
        bar.style.height = cat.height + '%';
        bar.style.background = cat.color;
        bar.title = cat.label + ': ' + cat.height + '%';

        const percentageText = document.createElement('span');
        percentageText.innerText = cat.height + '%';
        percentageText.style.fontSize = '11px';
        percentageText.style.fontWeight = 'bold';
        percentageText.style.color = '#334155';

        const nameText = document.createElement('span');
        nameText.innerText = cat.label;
        nameText.style.fontSize = '11px';
        nameText.style.color = '#64748b';
        nameText.style.textAlign = 'center';

        barWrap.appendChild(bar);
        barWrap.appendChild(percentageText);
        barWrap.appendChild(nameText);
        container.appendChild(barWrap);
    });
}

function generateTopWorkshops(wsData) {
    const list = document.getElementById('topWorkshopsList');
    if (!list || !wsData || !wsData.labels) return;

    let topWorkshops = [];
    let maxVal = Math.max(...wsData.data, 1);
    wsData.labels.forEach((label, idx) => {
        topWorkshops.push({
            name: label,
            jobs: wsData.data[idx],
            progress: Math.round((wsData.data[idx] / maxVal) * 100)
        });
    });

    list.innerHTML = '';
    topWorkshops.forEach(ws => {
        const li = document.createElement('li');
        li.innerHTML = `
            <div class="workshop-row">
                <span>${ws.name}</span>
                <span>${ws.jobs} jobs</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width:${ws.progress}%"></div>
            </div>
        `;
        list.appendChild(li);
    });
}

function generateServiceDistribution(catData) {
    const donut = document.getElementById('serviceDistributionDonut');
    const legend = document.getElementById('serviceDistributionLegend');
    if (!donut || !legend || !catData || !catData.labels) return;

    const colors = ['#00288E', '#4C63B6', '#A9B5E5', '#e2e8f0', '#94a3b8'];
    let total = catData.data.reduce((a, b) => a + b, 0) || 1;
    let data = [];
    
    catData.labels.forEach((label, idx) => {
        data.push({
            label: label,
            percentage: Math.round((catData.data[idx] / total) * 100),
            color: colors[idx % colors.length]
        });
    });

    let gradientString = '';
    let currentDegree = 0;
    
    legend.innerHTML = '';

    data.forEach((item, index) => {
        const startPercentage = currentDegree;
        const endPercentage = currentDegree + item.percentage;
        
        gradientString += `${item.color} ${startPercentage}% ${endPercentage}%`;
        if (index < data.length - 1) {
            gradientString += ', ';
        }

        currentDegree = endPercentage;

        // Add to legend
        const legendItem = document.createElement('span');
        legendItem.className = 'legend-item';
        legendItem.innerHTML = `<i style="background:${item.color}"></i> ${item.label} (${item.percentage}%)`;
        legend.appendChild(legendItem);
    });

    donut.style.background = `conic-gradient(${gradientString})`;
}

function generateRecentActivity(activities) {
    const ul = document.querySelector('.activity-list');
    if (!ul || !activities) return;
    ul.innerHTML = '';
    
    if (activities.length === 0) {
        ul.innerHTML = '<li><div style="text-align:center;width:100%;color:#64748b;font-size:13px;padding:15px;">No recent activity found.</div></li>';
        return;
    }
    
    activities.forEach(act => {
        const li = document.createElement('li');
        li.innerHTML = `
            <span class="activity-icon icon-purple-soft"><i class="fa-solid fa-wrench"></i></span>
            <div>
                <p class="activity-title">${act.type}: ${act.desc}</p>
                <p class="activity-time">${act.time}</p>
            </div>
        `;
        ul.appendChild(li);
    });
}
