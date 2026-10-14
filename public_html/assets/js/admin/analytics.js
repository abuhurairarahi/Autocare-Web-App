/**
 * analytics.js
 */

document.addEventListener("DOMContentLoaded", () => {
    initAnalytics();
});

function initAnalytics() {
    const API_URL = '../../api/admin-api/analytics-api.php';

    const avgTimeEl = document.getElementById('avg-service-time');
    const custSatEl = document.getElementById('customer-satisfaction');
    const totalServEl = document.getElementById('total-services');
    const reworkRateEl = document.getElementById('rework-rate');

    const trendBars = document.getElementById('trend-bars');
    const topWorkshopsList = document.getElementById('top-workshops-list');
    
    const mechanicsTbody = document.getElementById('mechanics-tbody');
    const mechanicSearch = document.getElementById('mechanic-search');
    const mechanicFilter = document.getElementById('mechanic-filter');
    const exportBtn = document.getElementById('export-btn');

    let currentSearch = '';
    let currentFilter = 'All';

    function fetchAnalytics() {
        let url = `${API_URL}?action=data&search=${encodeURIComponent(currentSearch)}&filter=${encodeURIComponent(currentFilter)}`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update Stats
                    if (avgTimeEl) avgTimeEl.innerText = data.stats.avg_time;
                    if (custSatEl) custSatEl.innerText = data.stats.rating + "/5.0";
                    if (totalServEl) totalServEl.innerText = data.stats.total;
                    if (reworkRateEl) reworkRateEl.innerText = data.stats.rework;
                    
                    renderTrends(data.trends);
                    renderWorkshops(data.workshops);
                    renderMechanics(data.mechanics);
                }
            })
            .catch(console.error);
    }

    function renderTrends(trends) {
        if (!trendBars) return;
        trendBars.innerHTML = '';
        
        // Calculate max to scale
        let maxCount = 1;
        trends.forEach(t => { if (t.count > maxCount) maxCount = t.count; });
        
        // At least render some columns
        if (trends.length === 0) {
            trendBars.innerHTML = '<span>No recent data</span>';
            return;
        }

        trends.forEach((t, i) => {
            const pct = Math.max(10, (t.count / maxCount) * 100);
            const isLast = i === trends.length - 1;
            const barClass = isLast ? 'bar active-bar' : 'bar';
            
            trendBars.innerHTML += `
                <div class="bar-col">
                    <div class="${barClass}" style="height: ${pct}%; ${isLast ? 'background-color: var(--accent-blue);' : ''}"></div>
                    <span>${t.day_name}</span>
                </div>
            `;
        });
    }

    function renderWorkshops(workshops) {
        if (!topWorkshopsList) return;
        topWorkshopsList.innerHTML = '';
        
        const medals = ['gold', 'silver', 'bronze'];
        workshops.forEach((w, index) => {
            const medal = medals[index] || 'bronze';
            topWorkshopsList.innerHTML += `
                <div class="ranking-item">
                    <span class="rank ${medal}">${index + 1}</span>
                    <div class="workshop-info">
                        <span class="workshop-name">${w.name}</span>
                        <div class="progress-bar">
                            <div class="progress" style="width: ${w.score}%;"></div>
                        </div>
                    </div>
                    <span class="workshop-score">${w.score}%</span>
                </div>
            `;
        });
    }

    function renderMechanics(mechanics) {
        if (!mechanicsTbody) return;
        mechanicsTbody.innerHTML = '';
        
        // Handle search alert if no data found
        if (mechanics.length === 0) {
            if (currentSearch !== '') {
                alert("No Data Found!");
                // Clear search manually if wanted, but keeping it shows empty state
            }
            mechanicsTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No mechanics match your criteria.</td></tr>';
            return;
        }

        mechanics.forEach((m, index) => {
            const parts = m.name.split(' ');
            const initials = parts.map(n => n[0]).join('').substring(0, 2).toUpperCase();
            const avatarBg = index % 2 === 0 ? 'blue-bg' : 'peach-bg';
            
            let statusBadge = 'active';
            let statusIcon = 'fa-circle';
            let statusText = 'Active';
            if (m.status === 'Suspended') {
                statusBadge = 'break'; // Reusing CSS 'break' for suspended
                statusText = 'Suspended';
            } else if (m.status === 'Pending') {
                statusBadge = 'break';
                statusText = 'Pending';
            }

            mechanicsTbody.innerHTML += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar ${avatarBg}">${initials}</span>
                            <span>${m.name}</span>
                        </div>
                    </td>
                    <td>${m.workshop}</td>
                    <td>${m.jobs}</td>
                    <td>${m.avg_time}</td>
                    <td>
                        <div class="score-cell">
                            <div class="progress-bar mini">
                                <div class="progress green" style="width: ${m.score}%;"></div>
                            </div>
                            <span>${m.score}</span>
                        </div>
                    </td>
                    <td><span class="status-badge ${statusBadge}"><i class="fa-solid ${statusIcon}"></i> ${statusText}</span></td>
                </tr>
            `;
        });
    }

    if (mechanicSearch) {
        mechanicSearch.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                currentSearch = mechanicSearch.value.trim();
                fetchAnalytics();
            }
        });
    }

    if (mechanicFilter) {
        mechanicFilter.addEventListener('change', () => {
            currentFilter = mechanicFilter.value;
            fetchAnalytics();
        });
    }
    
    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            window.location.href = `${API_URL}?action=export&search=${encodeURIComponent(currentSearch)}&filter=${encodeURIComponent(currentFilter)}`;
        });
    }

    // Load initial data
    fetchAnalytics();
}
