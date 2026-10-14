/**
 * revenue-reports.js
 */

document.addEventListener("DOMContentLoaded", () => {
    initRevenueReports();
});

function initRevenueReports() {
    const API_URL = '../../api/admin-api/revenue-reports-api.php';

    const monthSelect = document.getElementById('month-select');
    const yearSelect = document.getElementById('year-select');
    const locationSelect = document.getElementById('location-select');
    const applyBtn = document.getElementById('apply-btn');
    
    const pdfBtn = document.getElementById('pdf-btn');
    const csvBtn = document.getElementById('csv-btn');
    const reportContainer = document.getElementById('report-container');

    let reportGenerated = false;
    let currentMonth = '';
    let currentYear = '';
    let currentLocation = '';

    applyBtn.addEventListener('click', () => {
        currentMonth = monthSelect.value;
        currentYear = yearSelect.value;
        currentLocation = locationSelect.value;

        fetchReport(currentMonth, currentYear, currentLocation);
    });

    function fetchReport(month, year, loc) {
        // Show loading state
        reportContainer.innerHTML = '<p style="text-align:center; color:var(--text-muted);">Generating report...</p>';
        
        const url = `${API_URL}?action=report&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}&location=${encodeURIComponent(loc)}`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderReport(data);
                    reportGenerated = true;
                } else {
                    reportContainer.innerHTML = `<p style="text-align:center; color:red;">Error: ${data.error}</p>`;
                    reportGenerated = false;
                }
            })
            .catch(err => {
                console.error(err);
                reportContainer.innerHTML = `<p style="text-align:center; color:red;">Failed to load report.</p>`;
                reportGenerated = false;
            });
    }

    function renderReport(data) {
        let rowsHtml = '';
        if (data.rows.length === 0) {
            rowsHtml = '<tr><td colspan="5" style="text-align:center;">No transactions found for the selected filters.</td></tr>';
        } else {
            data.rows.forEach(row => {
                rowsHtml += `
                    <tr>
                        <td>#${row.id}</td>
                        <td>${row.transaction_date}</td>
                        <td>${row.workshop_location}</td>
                        <td>${row.service_category}</td>
                        <td><strong>&#2547;${parseFloat(row.amount).toLocaleString()}</strong></td>
                    </tr>
                `;
            });
        }

        const html = `
            <div class="report-summary">
                <div class="summary-box">
                    <h4>TOTAL REVENUE</h4>
                    <h2>&#2547;${parseFloat(data.total_revenue).toLocaleString()}</h2>
                </div>
                <div class="summary-box">
                    <h4>TOTAL TRANSACTIONS</h4>
                    <h2>${data.total_transactions}</h2>
                </div>
            </div>
            <div class="report-table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>TRANSACTION ID</th>
                            <th>DATE</th>
                            <th>WORKSHOP</th>
                            <th>CATEGORY</th>
                            <th>AMOUNT</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        `;
        
        reportContainer.innerHTML = html;
    }

    pdfBtn.addEventListener('click', () => {
        if (!reportGenerated) {
            alert("No report is generated");
            return;
        }
        const url = `${API_URL}?action=pdf&month=${encodeURIComponent(currentMonth)}&year=${encodeURIComponent(currentYear)}&location=${encodeURIComponent(currentLocation)}`;
        window.open(url, '_blank');
    });

    csvBtn.addEventListener('click', () => {
        if (!reportGenerated) {
            alert("No report is generated");
            return;
        }
        const url = `${API_URL}?action=csv&month=${encodeURIComponent(currentMonth)}&year=${encodeURIComponent(currentYear)}&location=${encodeURIComponent(currentLocation)}`;
        window.location.href = url;
    });
}
