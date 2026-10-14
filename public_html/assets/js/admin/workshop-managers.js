/**
 * workshop-managers.js
 * Specific interactions and dynamic functionality for the workshop-managers page.
 */

document.addEventListener("DOMContentLoaded", () => {
    initSpecificInteractions();
    initManagersFunctionality();
});

function initSpecificInteractions() {
    const cards = document.querySelectorAll('.stat-card, .metric-card, .card');
    cards.forEach(card => {
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
}

function initManagersFunctionality() {
    let currentPage = 1;
    let currentData = [];

    const tableBody = document.getElementById('managers-table-body');
    const searchInput = document.getElementById('search-input');
    const statusFilter = document.getElementById('status-filter');
    const exportBtn = document.getElementById('export-btn');

    // Modals
    const modal = document.getElementById('manager-modal');
    const addBtn = document.getElementById('add-manager-btn');
    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-modal');
    const form = document.getElementById('manager-form');
    const modalTitle = document.getElementById('modal-title');
    const dateInput = document.getElementById('manager-joining-date');

    const API_URL = '../../api/admin-api/workshop-managers-api.php';

    function fetchManagers(page = 1) {
        currentPage = page;
        const search = searchInput.value;
        const status = statusFilter.value;
        
        let url = `${API_URL}?action=list&page=${page}`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (status && status !== 'All') url += `&status=${encodeURIComponent(status)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    currentData = data.data;
                    renderTable(data.data);
                    renderPagination(data.total, data.page, data.limit);
                    
                    if (search.trim() !== '' && data.data.length === 0) {
                        alert("No Data Found!");
                    }
                }
            })
            .catch(err => console.error("Error fetching managers:", err));
    }

    function loadWorkshops() {
        fetch(API_URL + '?action=list_workshops')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    const select = document.getElementById('manager-workshop');
                    select.innerHTML = '<option value="">Unassigned</option>';
                    data.data.forEach(w => {
                        const opt = document.createElement('option');
                        opt.value = w.id;
                        opt.textContent = w.name;
                        select.appendChild(opt);
                    });
                } else {
                    console.error("API Error: ", data.error);
                }
            })
            .catch(err => console.error("Error fetching workshops:", err));
    }

    function renderTable(data) {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        
        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No managers found.</td></tr>';
            return;
        }

        data.forEach(mgr => {
            const tr = document.createElement('tr');
            
            const joinDate = mgr.created_at ? new Date(mgr.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : 'N/A';
            const nameParts = mgr.name ? mgr.name.split(' ') : ['U'];
            const initials = nameParts.map(n => n[0]).join('').substring(0, 2).toUpperCase();
            
            let statusClass = 'status-suspended';
            if (mgr.status === 'Active') statusClass = 'status-active';
            if (mgr.status === 'Pending') statusClass = 'status-pending';
            
            tr.innerHTML = `
                <td class="id-cell">#WM-${mgr.id}</td>
                <td>
                  <div class="name-cell">
                    <span class="avatar-sm">${initials}</span>
                    <span class="name-text">${mgr.name}</span>
                  </div>
                </td>
                <td>
                  <div class="contact-cell">
                    <span>${mgr.email}</span>
                    <span class="muted">${mgr.phone}</span>
                  </div>
                </td>
                <td>${mgr.workshop_name || 'Unassigned'}</td>
                <td>
                  <div class="status-pill ${statusClass}">
                    ${mgr.status}
                  </div>
                </td>
                <td>${joinDate}</td>
                <td class="col-actions">
                    <button class="btn btn-update update-btn" data-id="${mgr.id}">✎ Update</button>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        document.querySelectorAll('.update-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.target.getAttribute('data-id');
                const mgr = currentData.find(m => m.id == id);
                if (mgr) openUpdateModal(mgr);
            });
        });


    }

    function renderPagination(total, page, limit) {
        const totalPages = Math.ceil(total / limit);
        const start = (page - 1) * limit + 1;
        const end = Math.min(start + limit - 1, total);
        
        document.getElementById('entries-info').innerText = `Showing ${total > 0 ? start : 0} to ${end} of ${total} entries`;
        
        const pagination = document.getElementById('pagination');
        pagination.innerHTML = '';

        if (totalPages <= 1) return;

        const prevBtn = document.createElement('button');
        prevBtn.className = 'page-btn';
        prevBtn.innerText = '‹';
        prevBtn.disabled = page === 1;
        prevBtn.onclick = () => fetchManagers(page - 1);
        pagination.appendChild(prevBtn);

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `page-btn ${i === page ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => fetchManagers(i);
            pagination.appendChild(btn);
        }

        const nextBtn = document.createElement('button');
        nextBtn.className = 'page-btn';
        nextBtn.innerText = '›';
        nextBtn.disabled = page === totalPages;
        nextBtn.onclick = () => fetchManagers(page + 1);
        pagination.appendChild(nextBtn);
    }

    function openModal() {
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
        form.reset();
    }

    function openAddModal() {
        modalTitle.innerText = "Add New Manager";
        document.getElementById('manager-id').value = '';
        
        // Auto-fill joining date
        dateInput.value = new Date().toISOString().split('T')[0];
        // Note: readOnly is set so user cannot change the auto-filled date as requested
        dateInput.readOnly = true; 
        
        openModal();
    }

    function openUpdateModal(mgr) {
        modalTitle.innerText = "Update Manager";
        document.getElementById('manager-id').value = mgr.id;
        document.getElementById('manager-name').value = mgr.name;
        document.getElementById('manager-email').value = mgr.email;
        document.getElementById('manager-phone').value = mgr.phone;
        document.getElementById('manager-workshop').value = mgr.workshop_id || '';
        document.getElementById('manager-status').value = mgr.status;
        
        const datePart = mgr.created_at ? mgr.created_at.split(' ')[0] : '';
        dateInput.value = datePart;
        dateInput.readOnly = true;
        
        openModal();
    }

    if (addBtn) addBtn.addEventListener('click', openAddModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            const id = formData.get('id');
            formData.append('action', id ? 'update' : 'add');

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    fetchManagers(currentPage);
                    if (typeof showToast === 'function') {
                        showToast(`Manager ${id ? 'updated' : 'added'} successfully!`);
                    }
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => console.error("Form submit error:", err));
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault();
                fetchManagers(1);
            }
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => fetchManagers(1));
    }

    if (exportBtn) {
        exportBtn.addEventListener('click', () => {
            if (currentData.length === 0) return alert("No data to export.");
            
            let csvContent = "Manager ID,Name,Contact Info,Assigned Workshop,Status,Joining Date\n";
            currentData.forEach(mgr => {
                const id = `#WM-${mgr.id}`;
                const name = `"${mgr.name}"`;
                const contact = `"${mgr.email} / ${mgr.phone}"`;
                const workshop = `"${mgr.workshop_name || 'Unassigned'}"`;
                const status = `"${mgr.status}"`;
                const date = `"${mgr.created_at}"`;
                
                csvContent += `${id},${name},${contact},${workshop},${status},${date}\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.setAttribute("href", url);
            link.setAttribute("download", "workshop_managers_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // Initial fetch
    loadWorkshops();
    fetchManagers(1);
}
