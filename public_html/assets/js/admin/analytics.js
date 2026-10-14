/**
 * analytics.js
 * Specific interactions and dynamic functionality for the analytics page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("analytics page loaded successfully.");
    initSpecificInteractions();
    initExportAnalytics();
    loadAnalyticsFromDB();
});

function initSpecificInteractions() {


    // 2. Wire up specific page buttons
    const primaryBtns = document.querySelectorAll('.primary-btn, .btn-primary');
    primaryBtns.forEach(btn => {
        // Only attach if it doesn't already have an action from global admin.js
        if (!btn.hasAttribute('data-wired')) {
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

function initExportAnalytics() {
    const exportBtn = document.getElementById('export-analytics-btn');
    if (!exportBtn) return;

    exportBtn.addEventListener('click', () => {
        // Find the main dashboard wrapper to convert to PDF
        const element = document.querySelector('.dashboard-body');
        if (!element) return;
        
        if (typeof showToast === 'function') {
            showToast("Generating PDF... Please wait.");
        }

        const opt = {
            margin:       0.5,
            filename:     'analytics_report.pdf',
            image:        { type: 'jpeg', quality: 0.98 },
            html2canvas:  { scale: 2, useCORS: true, windowWidth: 1200 },
            jsPDF:        { unit: 'in', format: 'letter', orientation: 'landscape' }
        };

        // Use html2pdf to generate and download the PDF
        html2pdf().set(opt).from(element).save().then(() => {
            if (typeof showToast === 'function') {
                showToast("PDF Downloaded Successfully!");
            }
        });
    });
}

async function loadAnalyticsFromDB() {
    if (!window.adminApi) return;
    
    try {
        const response = await window.adminApi.request('analytics.php', 'GET');
        if (!response) return;

        // 1. Top Metrics
        document.getElementById('metric-avg-time').innerText = response.avg_service_time;
        document.getElementById('metric-satisfaction').innerText = response.customer_satisfaction;
        document.getElementById('metric-total-services').innerText = response.total_services;
        document.getElementById('metric-rework-rate').innerText = response.rework_rate;

        // 2. Trends
        const trendsContainer = document.getElementById('trends-bars-area');
        trendsContainer.innerHTML = '';
        response.trends.forEach(t => {
            trendsContainer.innerHTML += `
                <div class="bar-col">
                    <div class="bar" style="height: ${t.height}%;"></div><span>${t.day}</span>
                </div>
            `;
        });

        // 3. Top Workshops
        const workshopsContainer = document.getElementById('top-workshops-list');
        workshopsContainer.innerHTML = '';
        const ranks = ['gold', 'silver', 'bronze'];
        response.top_workshops.forEach((ws, idx) => {
            const rankClass = ranks[idx] || '';
            workshopsContainer.innerHTML += `
                <div class="ranking-item">
                    <span class="rank ${rankClass}">${idx + 1}</span>
                    <div class="workshop-info">
                        <span class="workshop-name">${ws.name}</span>
                        <div class="progress-bar">
                            <div class="progress" style="width: ${ws.progress}%;"></div>
                        </div>
                    </div>
                    <span class="workshop-score">${ws.progress}%</span>
                </div>
            `;
        });

        // 4. Mechanics
        const mechanicsTbody = document.getElementById('mechanics-tbody');
        mechanicsTbody.innerHTML = '';
        const colors = ['blue-bg', 'peach-bg', 'green-bg', 'purple-bg', 'orange-bg'];
        response.mechanics.forEach((m, idx) => {
            const initials = m.name.substring(0, 2).toUpperCase();
            const bgClass = colors[idx % colors.length];
            mechanicsTbody.innerHTML += `
                <tr>
                    <td>
                        <div class="user-cell">
                            <span class="avatar ${bgClass}">${initials}</span>
                            <span>${m.name}</span>
                        </div>
                    </td>
                    <td>${m.workshop_name}</td>
                    <td>${m.jobs_completed}</td>
                    <td>${m.avg_time_str}</td>
                    <td>
                        <div class="score-cell">
                            <div class="progress-bar mini">
                                <div class="progress green" style="width: ${m.efficiency}%;"></div>
                            </div>
                            <span>${m.efficiency}</span>
                        </div>
                    </td>
                    <td><span class="status-badge active"><i class="fa-solid fa-circle"></i> Active</span></td>
                </tr>
            `;
        });
    } catch (e) {
        console.error("Failed to load analytics data", e);
    }
}
