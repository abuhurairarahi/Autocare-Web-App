/**
 * revenue-reports.js
 * Specific interactions and dynamic functionality for the revenue-reports page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("revenue-reports page loaded successfully.");
    initSpecificInteractions();
    initReportLogic();
});

function initSpecificInteractions() {
    // 4. Handle specific filters
    const selects = document.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', (e) => {
            if (typeof showToast === 'function') {
                // showToast("Data filtered by: " + e.target.options[e.target.selectedIndex].text);
            }
        });
    });
}

let currentReportData = [];

function initReportLogic() {
    const applyBtn = document.getElementById('apply-filters-btn');
    const reportContainer = document.getElementById('report-container');
    const tbody = document.getElementById('report-tbody');
    const totalBadge = document.getElementById('total-revenue-badge');
    const reportTitle = document.getElementById('report-title');

    const btnCsv = document.getElementById('btn-download-csv');
    const btnPdf = document.getElementById('btn-generate-pdf');

    // Mark CSV button as wired so global admin.js doesn't override
    if(btnCsv) btnCsv.setAttribute('data-wired', 'true');
    if(btnPdf) btnPdf.setAttribute('data-wired', 'true');

    applyBtn.addEventListener('click', async () => {
        const month = document.getElementById('month-select').value;
        const year = document.getElementById('year-select').value;
        const location = document.getElementById('location-select').value;

        if (typeof showToast === 'function') {
            showToast('Generating report...', 'info');
        }

        // Fetch data from DB
        let fetchedData = [];
        if (window.adminApi) {
            fetchedData = await window.adminApi.request('revenue-reports.php', 'GET');
        }

        // Map DB fields to what the UI expects, handle empty arrays if nothing returned
        currentReportData = (fetchedData && fetchedData.length > 0) ? fetchedData.map(dbItem => ({
            date: new Date(dbItem.issued_date).toLocaleDateString(),
            txId: 'INV-' + dbItem.invoice_id,
            category: 'Auto Repair', // Could be dynamic if we joined categories
            customer: 'Customer ' + dbItem.customer_id,
            amount: parseFloat(dbItem.total_amount),
            status: dbItem.status === 'Paid' ? 'Completed' : (dbItem.status === 'Unpaid' ? 'Pending' : 'Failed')
        })) : [];

        // Update UI
        reportTitle.innerText = `Revenue Report - ${month} ${year} (${location})`;
        
        let totalRevenue = 0;
        tbody.innerHTML = '';

        if(currentReportData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No transactions found for this period.</td></tr>';
        } else {
            currentReportData.forEach(item => {
                totalRevenue += item.amount;
                let statusBadge = item.status === 'Completed' ? 'badge-green' : (item.status === 'Pending' ? 'badge-yellow' : 'badge-red');
                
                tbody.innerHTML += `
                    <tr>
                        <td>${item.date}</td>
                        <td><strong>${item.txId}</strong></td>
                        <td>${item.category}</td>
                        <td>${item.customer}</td>
                        <td>৳${item.amount.toLocaleString('en-IN')}</td>
                        <td><span class="badge ${statusBadge}">${item.status}</span></td>
                    </tr>
                `;
            });
        }

        totalBadge.innerText = `Total: ৳${totalRevenue.toLocaleString('en-IN')}`;
        reportContainer.style.display = 'block';
        
        // Scroll to report
        setTimeout(() => {
            reportContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    });

    if(btnCsv) {
        btnCsv.addEventListener('click', () => {
            if(currentReportData.length === 0) {
                if(typeof showToast === 'function') showToast("Please generate a report first.", "error");
                return;
            }
            downloadCSV(currentReportData);
        });
    }

    if(btnPdf) {
        btnPdf.addEventListener('click', () => {
            if(currentReportData.length === 0) {
                if(typeof showToast === 'function') showToast("Please generate a report first.", "error");
                return;
            }
            downloadPDFMock();
        });
    }
}

function generateMockReportData(month, year, location) {
    const categories = ['Routine Maint.', 'Engine Repair', 'Electrical', 'Body Shop', 'HVAC'];
    const statuses = ['Completed', 'Completed', 'Completed', 'Pending', 'Refunded'];
    const customers = ['John Doe', 'Jane Smith', 'Rahim Uddin', 'Karim Ali', 'Sarah Connor'];
    
    // Randomize row count based on location
    let rowCount = location === 'All Workshops' ? 12 : Math.floor(Math.random() * 5) + 4;
    
    const data = [];
    for(let i=0; i<rowCount; i++) {
        let day = Math.floor(Math.random() * 28) + 1;
        data.push({
            date: `${day} ${month.substring(0,3)}, ${year}`,
            txId: 'TXN-' + Math.floor(100000 + Math.random() * 900000),
            category: categories[Math.floor(Math.random() * categories.length)],
            customer: customers[Math.floor(Math.random() * customers.length)],
            amount: Math.floor(Math.random() * 15000) + 1000,
            status: statuses[Math.floor(Math.random() * statuses.length)]
        });
    }
    
    // Sort by date (mock sorting)
    data.sort((a,b) => parseInt(a.date) - parseInt(b.date));
    
    return data;
}

function downloadCSV(data) {
    const headers = ["Date", "Transaction ID", "Service Category", "Customer", "Amount", "Status"];
    let csvContent = headers.join(",") + "\n";
    
    data.forEach(row => {
        let rowData = [
            `"${row.date}"`, 
            `"${row.txId}"`, 
            `"${row.category}"`, 
            `"${row.customer}"`, 
            `"${row.amount}"`, 
            `"${row.status}"`
        ];
        csvContent += rowData.join(",") + "\n";
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `Revenue_Report.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    if (typeof showToast === 'function') {
        showToast('CSV downloaded successfully!', 'success');
    }
}

function downloadPDFMock() {
    if (typeof html2pdf === 'undefined') {
        if (typeof showToast === 'function') {
            showToast('PDF library not loaded.', 'error');
        }
        return;
    }

    const reportElement = document.getElementById('report-container');
    
    // Temporarily adjust styles for better PDF rendering
    const originalShadow = reportElement.querySelector('.panel').style.boxShadow;
    reportElement.querySelector('.panel').style.boxShadow = 'none';

    if (typeof showToast === 'function') {
        showToast('Generating PDF, please wait...', 'info');
    }

    const opt = {
        margin:       10,
        filename:     'Revenue_Report.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2 },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    html2pdf().set(opt).from(reportElement).save().then(() => {
        // Restore styles
        reportElement.querySelector('.panel').style.boxShadow = originalShadow;
        if (typeof showToast === 'function') {
            showToast('PDF downloaded successfully!', 'success');
        }
    });
}
