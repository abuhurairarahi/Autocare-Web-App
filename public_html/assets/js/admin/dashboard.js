/**
 * dashboard.js
 * Database-backed dashboard data, live updates, charts, and activity navigation.
 */

const dashboardApiUrl = '../../api/admin-api/dashboard-api.php';
let dashboardData = [];
let revenueChart;
let activityModal;
let activitiesForModal = [];

function currency(value) {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 0,
    }).format(Number(value) || 0);
}

function formatDate(value) {
    if (!value) return 'Recently';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Recently';
    return new Intl.DateTimeFormat('en', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}

function renderStats(data) {
    document.getElementById('total-managers').textContent = Number(data.totalManagers || 0).toLocaleString('en-BD');
    document.getElementById('pending-approvals').textContent = Number(data.pendingMechanicApprovals || 0).toLocaleString('en-BD');
    document.getElementById('active-workshops').textContent = Number(data.activeWorkshops || 0).toLocaleString('en-BD');
    document.getElementById('monthly-revenue').textContent = currency(data.monthlyRevenue || 0);
}

function drawRevenueChart(points) {
    const canvas = document.getElementById('revenue-chart');
    const loading = document.getElementById('revenue-chart-loading');
    if (!canvas) return;

    const context = canvas.getContext('2d');
    const width = canvas.clientWidth || 700;
    const height = canvas.clientHeight || 320;
    const dpr = window.devicePixelRatio || 1;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    context.setTransform(dpr, 0, 0, dpr, 0, 0);
    context.clearRect(0, 0, width, height);

    const padding = { top: 24, right: 22, bottom: 42, left: 58 };
    const plotWidth = width - padding.left - padding.right;
    const plotHeight = height - padding.top - padding.bottom;

    loading.hidden = points.length > 0;
    if (!points.length) {
        context.fillStyle = '#64748b';
        context.font = '14px sans-serif';
        context.textAlign = 'center';
        context.fillText('No paid revenue recorded yet.', width / 2, height / 2);
        return;
    }

    const values = points.map(point => Number(point.revenue || 0));
    const maxValue = Math.max(...values, 1);
    const roundedMax = Math.ceil(maxValue / 1000) * 1000 || 1;
    const xStep = points.length > 1 ? plotWidth / (points.length - 1) : plotWidth;

    context.strokeStyle = '#e2e8f0';
    context.lineWidth = 1;
    context.font = '12px sans-serif';
    context.fillStyle = '#64748b';
    context.textAlign = 'right';

    for (let i = 0; i <= 4; i++) {
        const y = padding.top + (plotHeight / 4) * i;
        const value = roundedMax - (roundedMax / 4) * i;
        context.beginPath();
        context.moveTo(padding.left, y);
        context.lineTo(width - padding.right, y);
        context.stroke();
        context.fillText(currency(value).replace(/\.00$/, ''), padding.left - 8, y + 4);
    }

    const graphPoints = points.map((point, index) => ({
        x: padding.left + index * xStep,
        y: padding.top + plotHeight - (Number(point.revenue || 0) / roundedMax) * plotHeight,
        label: new Intl.DateTimeFormat('en', { month: 'short', year: '2-digit' }).format(new Date(`${point.month}-01T00:00:00`)),
    }));

    const gradient = context.createLinearGradient(0, padding.top, 0, height - padding.bottom);
    gradient.addColorStop(0, 'rgba(0, 40, 142, 0.22)');
    gradient.addColorStop(1, 'rgba(0, 40, 142, 0)');

    context.beginPath();
    context.moveTo(graphPoints[0].x, height - padding.bottom);
    graphPoints.forEach(point => context.lineTo(point.x, point.y));
    context.lineTo(graphPoints[graphPoints.length - 1].x, height - padding.bottom);
    context.closePath();
    context.fillStyle = gradient;
    context.fill();

    context.beginPath();
    graphPoints.forEach((point, index) => {
        if (index === 0) context.moveTo(point.x, point.y);
        else context.lineTo(point.x, point.y);
    });
    context.strokeStyle = '#00288E';
    context.lineWidth = 3;
    context.lineJoin = 'round';
    context.lineCap = 'round';
    context.stroke();

    graphPoints.forEach(point => {
        context.beginPath();
        context.arc(point.x, point.y, 4, 0, Math.PI * 2);
        context.fillStyle = '#fff';
        context.fill();
        context.strokeStyle = '#00288E';
        context.lineWidth = 2;
        context.stroke();

        context.fillStyle = '#475569';
        context.textAlign = 'center';
        context.font = '11px sans-serif';
        context.fillText(point.label, point.x, height - 14);
    });
}

function activityIcon(category) {
    const icons = {
        mechanic: 'fa-user',
        inventory: 'fa-boxes-stacked',
        appointment: 'fa-calendar-check',
        invoice: 'fa-file-invoice-dollar',
        broadcast: 'fa-bullhorn',
    };
    return icons[category] || 'fa-circle';
}

function renderActivities(items) {
    const list = document.getElementById('activity-list');
    if (!list) return;
    activitiesForModal = items;
    list.innerHTML = '';

    const visibleItems = items.slice(0, 4);
    visibleItems.forEach(item => {
        const listItem = document.createElement('li');
        const link = document.createElement('a');
        link.className = 'activity-link';
        link.href = item.link || '#';
        link.target = '_self';
        link.innerHTML = `
            <span class="activity-icon icon-blue-soft"><i class="fa-solid ${activityIcon(item.category)}"></i></span>
            <span class="activity-copy">
                <span class="activity-title"></span>
                <span class="activity-detail"></span>
                <span class="activity-time"></span>
            </span>`;
        link.querySelector('.activity-title').textContent = item.title;
        link.querySelector('.activity-detail').textContent = item.detail;
        link.querySelector('.activity-time').textContent = formatDate(item.created_at);
        list.appendChild(listItem);
        listItem.appendChild(link);
    });

    if (!visibleItems.length) {
        list.innerHTML = '<li class="empty-state">No recent activity found.</li>';
    }
}

function openActivityModal() {
    if (!activityModal) {
        activityModal = document.createElement('div');
        activityModal.className = 'modal-dialog';
        activityModal.setAttribute('role', 'dialog');
        activityModal.setAttribute('aria-modal', 'true');
        activityModal.setAttribute('aria-labelledby', 'activity-modal-title');
        activityModal.innerHTML = `
            <div class="modal-backdrop" data-close-modal></div>
            <div class="modal-card">
                <div class="modal-header">
                    <div><h2 id="activity-modal-title">All Recent Activities</h2><p>Live system notifications</p></div>
                    <button type="button" class="modal-close" aria-label="Close">&times;</button>
                </div>
                <ul class="modal-activity-list" id="modal-activity-list"></ul>
            </div>`;
        document.body.appendChild(activityModal);
        activityModal.querySelector('.modal-close').addEventListener('click', closeActivityModal);
        activityModal.querySelector('[data-close-modal]').addEventListener('click', closeActivityModal);
    }

    const modalList = activityModal.querySelector('#modal-activity-list');
    modalList.innerHTML = '';
    activitiesForModal.forEach(item => {
        const row = document.createElement('li');
        const link = document.createElement('a');
        link.href = item.link || '#';
        link.innerHTML = `<span class="modal-activity-title"></span><span class="modal-activity-detail"></span><span class="modal-activity-time"></span>`;
        link.querySelector('.modal-activity-title').textContent = item.title;
        link.querySelector('.modal-activity-detail').textContent = item.detail;
        link.querySelector('.modal-activity-time').textContent = formatDate(item.created_at);
        row.appendChild(link);
        modalList.appendChild(row);
    });
    activityModal.classList.add('open');
    document.body.classList.add('modal-open');
}

function closeActivityModal() {
    if (!activityModal) return;
    activityModal.classList.remove('open');
    document.body.classList.remove('modal-open');
}

function renderTopWorkshops(workshops) {
    const list = document.getElementById('top-workshops-list');
    if (!list) return;
    const maxJobs = Math.max(...workshops.map(item => Number(item.jobs || 0)), 1);
    list.innerHTML = '';

    workshops.forEach((workshop, index) => {
        const item = document.createElement('li');
        const percentage = Math.max(8, Math.round((Number(workshop.jobs || 0) / maxJobs) * 100));
        item.innerHTML = `
            <div class="workshop-row"><span></span><span></span></div>
            <div class="progress-track"><div class="progress-fill"></div></div>`;
        item.querySelector('.workshop-row span:first-child').textContent = workshop.name;
        item.querySelector('.workshop-row span:last-child').textContent = `${Number(workshop.jobs || 0).toLocaleString('en-BD')} jobs`;
        item.querySelector('.progress-fill').style.width = `${percentage}%`;
        item.querySelector('.progress-fill').style.background = index === 0 ? '#00288E' : '#6E7FBE';
        list.appendChild(item);
    });
}

function renderServiceDistribution(items) {
    const donut = document.getElementById('service-donut');
    const legend = document.getElementById('service-legend');
    if (!donut || !legend) return;

    const colors = ['#00288E', '#4C63B6', '#7C3AED', '#0EA5E9', '#F59E0B'];
    const total = items.reduce((sum, item) => sum + Number(item.count || 0), 0);
    let cursor = 0;
    const segments = items.map((item, index) => {
        const percentage = total ? (Number(item.count || 0) / total) * 100 : 0;
        const start = cursor;
        cursor += percentage;
        return `${colors[index % colors.length]} ${start}% ${cursor}%`;
    });

    donut.style.background = total ? `conic-gradient(${segments.join(', ')})` : '#e2e8f0';
    legend.innerHTML = '';
    items.forEach((item, index) => {
        const label = document.createElement('span');
        label.className = 'legend-item';
        label.innerHTML = `<i style="background:${colors[index % colors.length]}"></i><span></span>`;
        label.querySelector('span').textContent = `${item.category} (${Number(item.count || 0)})`;
        legend.appendChild(label);
    });

    const barChart = document.getElementById('services-by-category-chart');
    if (barChart) {
        barChart.innerHTML = '';
        if (!items.length) {
            barChart.innerHTML = '<span style="color:#64748b; font-size:14px; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:100%; text-align:center;">No services data available.</span>';
            return;
        }
        const maxCount = Math.max(...items.map(i => Number(i.count || 0)), 1);
        items.forEach((item, index) => {
            if (Number(item.count || 0) === 0) return; // Skip zero counts
            
            const wrapper = document.createElement('div');
            wrapper.style.display = 'flex';
            wrapper.style.flexDirection = 'column';
            wrapper.style.alignItems = 'center';
            wrapper.style.height = '100%';
            wrapper.style.justifyContent = 'flex-end';
            wrapper.style.gap = '6px';
            wrapper.style.flex = '1';

            const bar = document.createElement('div');
            bar.className = 'bar';
            const percentage = Math.max(8, (Number(item.count || 0) / maxCount) * 100);
            // subtract 20px for the text label height roughly
            bar.style.height = `calc(${percentage}% - 20px)`; 
            bar.style.background = colors[index % colors.length];
            bar.style.width = '100%';
            bar.style.maxWidth = '40px';
            bar.title = `${item.category}: ${item.count}`;

            const label = document.createElement('span');
            label.style.fontSize = '10px';
            label.style.color = '#64748b';
            label.style.textAlign = 'center';
            label.style.width = '100%';
            label.style.whiteSpace = 'nowrap';
            label.style.overflow = 'hidden';
            label.style.textOverflow = 'ellipsis';
            label.textContent = item.category;

            wrapper.appendChild(bar);
            wrapper.appendChild(label);
            barChart.appendChild(wrapper);
        });
    }
}

function renderDashboard(data) {
    dashboardData = data;
    renderStats(data.stats);
    drawRevenueChart(data.revenueTrend);
    renderActivities(data.activities);
    renderTopWorkshops(data.topWorkshops);
    renderServiceDistribution(data.serviceDistribution);
}

async function fetchDashboardData() {
    try {
        const response = await fetch(dashboardApiUrl, { cache: 'no-store' });
        if (!response.ok) throw new Error(`Dashboard API returned ${response.status}`);
        const data = await response.json();
        if (!data.success) throw new Error(data.error || 'Dashboard data was invalid.');
        renderDashboard(data);
    } catch (error) {
        console.error(error);
        showToast('Dashboard data is temporarily unavailable.', 'error');
    }
}

function initSpecificInteractions() {
    document.querySelectorAll('.stat-card, .metric-card, .card').forEach(card => {
        card.style.transition = 'transform 0.3s ease, box-shadow 0.3s ease';
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-5px)';
            card.style.boxShadow = '0 12px 24px rgba(0,0,0,0.15)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0)';
            card.style.boxShadow = 'var(--shadow, 0 4px 6px rgba(0,0,0,0.05))';
        });
    });

    document.getElementById('view-all-activities')?.addEventListener('click', openActivityModal);
    window.addEventListener('resize', () => {
        if (dashboardData.revenueTrend) drawRevenueChart(dashboardData.revenueTrend);
    });

    document.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', event => {
            if (typeof showToast === 'function') {
                showToast('Data filtered by: ' + event.target.options[event.target.selectedIndex].text);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initSpecificInteractions();
    fetchDashboardData();
    setInterval(fetchDashboardData, 15000);
});
