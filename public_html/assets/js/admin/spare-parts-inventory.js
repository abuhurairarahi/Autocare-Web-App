/**
 * spare-parts-inventory.js
 * Specific interactions and dynamic functionality for the spare-parts-inventory page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("spare-parts-inventory page loaded successfully.");
    initSpecificInteractions();
    initExportParts();
    initAddPartModal();
    loadInventoryFromDB();
});

async function loadInventoryFromDB() {
    const tbody = document.getElementById('inventory-tbody');
    if (!tbody || !window.adminApi) return;
    
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">Loading data...</td></tr>';
    const parts = await window.adminApi.request('spare-parts-inventory.php', 'GET');
    
    if (parts && parts.length > 0) {
        tbody.innerHTML = '';
        parts.forEach(p => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="part-sku">${p.sku}</span></td>
                <td><div style="font-weight: 600; color: #1e293b;">${p.name}</div></td>
                <td>${p.category || 'General'}</td>
                <td>OEM</td>
                <td><span class="stock-badge ${p.stock_quantity < p.reorder_level ? 'low-stock' : 'in-stock'}">${p.stock_quantity}</span></td>
                <td>৳${p.price}</td>
                <td>${p.supplier || 'AutoCare Suppliers'}</td>
                <td><span class="status-badge ${p.stock_quantity < p.reorder_level ? 'draft' : 'active'}">${p.stock_quantity < p.reorder_level ? 'Low Stock' : 'In Stock'}</span></td>
                <td>
                    <div class="action-btns">
                        <button class="btn-update" title="Restock">Restock</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No parts found in inventory.</td></tr>';
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


function initAddPartModal() {
    const mainAddBtn = document.getElementById('add-part-btn');
    const rowBtns = document.querySelectorAll('.btn-restock-row');
    const modal = document.getElementById('add-part-modal');
    const closeBtn = document.getElementById('close-part-modal-btn');
    const cancelBtn = document.getElementById('cancel-part-modal-btn');
    const form = document.getElementById('add-part-form');

    if (!modal) return;

    let currentRow = null;

    if (mainAddBtn) {
        mainAddBtn.addEventListener('click', () => {
            currentRow = null;
            document.getElementById('part-name-input').value = '';
            document.getElementById('part-quantity-input').value = '';
            modal.classList.add('active');
        });
    }

    const tbody = document.getElementById('inventory-tbody');
    if (tbody) {
        tbody.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-update');
            if (btn) {
                currentRow = e.target.closest('tr');
                if (currentRow) {
                    let partNameCell = currentRow.querySelector('td strong');
                    if(!partNameCell) partNameCell = currentRow.children[1];
                    const partName = partNameCell.innerText.trim();
                    document.getElementById('part-name-input').value = partName;
                    document.getElementById('part-quantity-input').value = '';
                }
                modal.classList.add('active');
            }
        });
    }

    const closeModal = () => { 
        modal.classList.remove('active'); 
        if(form) form.reset(); 
        currentRow = null;
    };

    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    if(cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    if(form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (currentRow) {
                const currentStockCell = currentRow.children[4];
                if(currentStockCell) {
                    let currentStock = parseInt(currentStockCell.innerText.replace(/\D/g, '')) || 0;
                    let added = parseInt(document.getElementById('part-quantity-input').value) || 0;
                    currentStockCell.innerText = currentStock + added;
                }
            }

            if (typeof showToast === 'function') {
                showToast("Part restocked successfully!");
            } else {
                alert("Part restocked successfully!");
            }
            closeModal();
        });
    }
}

function initExportParts() {
    const exportBtn = document.getElementById('export-parts-btn');
    if (!exportBtn) return;

    exportBtn.addEventListener('click', () => {
        const table = document.querySelector('table');
        if (!table) return;

        let csvContent = "data:text/csv;charset=utf-8,";
        
        const rows = table.querySelectorAll('tr');
        rows.forEach(row => {
            let rowData = [];
            const cols = row.querySelectorAll('th, td');
            
            for (let i = 0; i < cols.length - 1; i++) {
                let text = cols[i].innerText.replace(/(\r\n|\n|\r)/gm, " ").trim();
                text = text.replace(/"/g, '""');
                rowData.push('"' + text + '"');
            }
            csvContent += rowData.join(",") + "\r\n";
        });

        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "spare_parts_inventory.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        if (typeof showToast === 'function') {
            showToast("Export successful!");
        }
    });
}
