/**
 * spare-parts-inventory.js
 */

document.addEventListener("DOMContentLoaded", () => {
    initInventoryFunctionality();
});

function initInventoryFunctionality() {
    const API_URL = '../../api/admin-api/spare-parts-api.php';
    let currentPage = 1;

    // Elements
    const tableBody = document.getElementById('inventory-table-body');
    const tableInfo = document.getElementById('table-info');
    const paginationNumbers = document.getElementById('pagination-numbers');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    // Stats
    const totalPartsVal = document.getElementById('total-parts-val');
    const lowStockVal = document.getElementById('low-stock-val');
    const inventoryValueVal = document.getElementById('inventory-value-val');

    // Filters
    const filterCat = document.getElementById('filter-category');
    const filterBrand = document.getElementById('filter-brand');
    const filterStatus = document.getElementById('filter-status');
    const reloadBtn = document.getElementById('reload-btn');

    // Modals & Buttons
    const restockBtn = document.getElementById('restock-btn');
    const exportBtn = document.getElementById('export-btn');
    
    const restockModal = document.getElementById('restock-modal');
    const closeRestock = document.getElementById('close-restock');
    const cancelRestock = document.getElementById('cancel-restock');
    const restockForm = document.getElementById('restock-form');
    
    const updateModal = document.getElementById('update-modal');
    const closeUpdate = document.getElementById('close-update');
    const cancelUpdate = document.getElementById('cancel-update');
    const updateForm = document.getElementById('update-form');

    // Data lists
    const partsList = document.getElementById('parts-list');
    const restockCategory = document.getElementById('restock-category');
    const brandsList = document.getElementById('brands-list');

    function fetchMetadata() {
        fetch(`${API_URL}?action=metadata`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Populate Filters
                    data.categories.forEach(cat => {
                        const opt = new Option(cat, cat);
                        filterCat.add(opt);
                        restockCategory.add(new Option(cat, cat));
                    });
                    
                    data.brands.forEach(brand => {
                        const opt = new Option(brand, brand);
                        filterBrand.add(opt);
                        const listOpt = document.createElement('option');
                        listOpt.value = brand;
                        brandsList.appendChild(listOpt);
                    });
                    
                    data.parts.forEach(part => {
                        const listOpt = document.createElement('option');
                        listOpt.value = part;
                        partsList.appendChild(listOpt);
                    });
                }
            })
            .catch(console.error);
    }

    function fetchInventory(page = 1) {
        currentPage = page;
        const cat = filterCat.value;
        const brand = filterBrand.value;
        const status = filterStatus.value;
        
        let url = `${API_URL}?action=list&page=${page}&category=${encodeURIComponent(cat)}&brand=${encodeURIComponent(brand)}&status=${encodeURIComponent(status)}`;
        
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    renderTable(data.data);
                    renderPagination(data.total, data.page, data.limit);
                    updateStats(data.stats);
                }
            })
            .catch(console.error);
    }

    function renderTable(data) {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        
        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No parts found.</td></tr>';
            return;
        }

        data.forEach(part => {
            const tr = document.createElement('tr');
            
            let badgeClass = 'status-in-stock';
            let badgeText = 'IN STOCK';
            if (part.status === 'Low Stock') { badgeClass = 'status-low-stock'; badgeText = 'LOW STOCK'; }
            if (part.status === 'Out of Stock') { badgeClass = 'status-out-of-stock'; badgeText = 'OUT OF STOCK'; }
            
            tr.innerHTML = `
                <td class="part-id">${part.part_id}</td>
                <td><strong>${part.name}</strong></td>
                <td>${part.category}</td>
                <td>${part.brand}</td>
                <td>${part.quantity}</td>
                <td>&#2547;${parseFloat(part.unit_price).toLocaleString()}</td>
                <td>${part.supplier}</td>
                <td><span class="status-badge ${badgeClass}">${badgeText}</span></td>
                <td><button class="action-icon open-update-btn" data-id="${part.id}" data-name="${part.name}" data-qty="${part.quantity}"><i class="fa-solid fa-cart-shopping"></i></button></td>
            `;
            tableBody.appendChild(tr);
        });

        document.querySelectorAll('.open-update-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                const name = e.currentTarget.getAttribute('data-name');
                const qty = e.currentTarget.getAttribute('data-qty');
                
                document.getElementById('update-id').value = id;
                document.getElementById('update-name').value = name;
                document.getElementById('update-quantity').value = qty;
                
                updateModal.classList.add('active');
            });
        });
    }

    function renderPagination(total, page, limit) {
        const totalPages = Math.ceil(total / limit);
        const start = (page - 1) * limit + 1;
        const end = Math.min(start + limit - 1, total);
        
        if (tableInfo) {
            tableInfo.innerText = `Showing ${total > 0 ? start : 0} to ${end} of ${total} entries`;
        }
        
        if (paginationNumbers) paginationNumbers.innerHTML = '';

        if (prevBtn) {
            prevBtn.disabled = page === 1;
            prevBtn.onclick = () => fetchInventory(page - 1);
        }
        
        if (nextBtn) {
            nextBtn.disabled = page >= totalPages || total === 0;
            nextBtn.onclick = () => fetchInventory(page + 1);
        }

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `page-btn ${i === page ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => fetchInventory(i);
            paginationNumbers.appendChild(btn);
        }
    }

    function updateStats(stats) {
        if (!stats) return;
        if(totalPartsVal) totalPartsVal.innerText = parseInt(stats.total_parts || 0).toLocaleString();
        if(lowStockVal) lowStockVal.innerText = parseInt(stats.low_stock || 0).toLocaleString();
        if(inventoryValueVal) inventoryValueVal.innerText = '৳' + parseFloat(stats.inventory_value || 0).toLocaleString();
    }

    // Modal Actions
    const hideRestock = () => { restockModal.classList.remove('active'); restockForm.reset(); };
    const hideUpdate = () => { updateModal.classList.remove('active'); updateForm.reset(); };

    if (restockBtn) restockBtn.addEventListener('click', () => restockModal.classList.add('active'));
    if (closeRestock) closeRestock.addEventListener('click', hideRestock);
    if (cancelRestock) cancelRestock.addEventListener('click', hideRestock);
    
    if (closeUpdate) closeUpdate.addEventListener('click', hideUpdate);
    if (cancelUpdate) cancelUpdate.addEventListener('click', hideUpdate);

    // Filter Listeners
    [filterCat, filterBrand, filterStatus].forEach(f => {
        if (f) f.addEventListener('change', () => fetchInventory(1));
    });
    
    if (reloadBtn) reloadBtn.addEventListener('click', () => fetchInventory(currentPage));
    if (exportBtn) exportBtn.addEventListener('click', () => {
        const cat = filterCat.value;
        const brand = filterBrand.value;
        const status = filterStatus.value;
        window.location.href = `${API_URL}?action=export&category=${encodeURIComponent(cat)}&brand=${encodeURIComponent(brand)}&status=${encodeURIComponent(status)}`;
    });

    // Forms
    if (restockForm) {
        restockForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(restockForm);
            fd.append('action', 'restock');
            
            fetch(API_URL, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hideRestock();
                        fetchInventory(currentPage);
                    } else alert(data.error);
                }).catch(console.error);
        });
    }

    if (updateForm) {
        updateForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const fd = new FormData(updateForm);
            fd.append('action', 'update_stock');
            
            fetch(API_URL, { method: 'POST', body: fd })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hideUpdate();
                        fetchInventory(currentPage);
                    } else alert(data.error);
                }).catch(console.error);
        });
    }

    // Init
    fetchMetadata();
    fetchInventory();
}
