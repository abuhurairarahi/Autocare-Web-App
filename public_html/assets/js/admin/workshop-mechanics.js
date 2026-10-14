/**
 * workshop-mechanics.js
 * Dynamic interactions for Workshop Mechanics Page
 */

document.addEventListener("DOMContentLoaded", () => {
    initMechanicsFunctionality();
});

function initMechanicsFunctionality() {
    let currentPage = 1;
    let isSorted = false;

    const tableBody = document.getElementById('mechanics-table-body');
    const filterStatus = document.getElementById('filter-status');
    const sortBtn = document.getElementById('sort-btn');
    const tableInfo = document.getElementById('table-info');

    // Modal elements
    const modal = document.getElementById('mechanic-modal');
    const addBtn = document.getElementById('add-mechanic-btn');
    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-modal');
    const form = document.getElementById('mechanic-form');
    const dateInput = document.getElementById('joining-date');
    const workshopSelect = document.getElementById('workshop-select');

    // Pagination
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const paginationNumbers = document.getElementById('pagination-numbers');

    const API_URL = '../../api/admin-api/workshop-mechanics-api.php';

    function fetchWorkshops() {
        if (!workshopSelect) return;
        fetch(`${API_URL}?action=workshops`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    workshopSelect.innerHTML = '<option value="">Select Workshop...</option>';
                    data.data.forEach(w => {
                        const opt = document.createElement('option');
                        opt.value = w.id;
                        opt.textContent = w.name;
                        workshopSelect.appendChild(opt);
                    });
                }
            })
            .catch(err => console.error("Error fetching workshops:", err));
    }

    function fetchMechanics(page = 1) {
        currentPage = page;
        const status = filterStatus.value;
        
        let url = `${API_URL}?action=list&page=${page}`;
        if (status && status !== 'All') url += `&status=${encodeURIComponent(status)}`;
        if (isSorted) url += `&sort=true`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    renderTable(data.data);
                    renderPagination(data.total, data.page, data.limit);
                }
            })
            .catch(err => console.error("Error fetching mechanics:", err));
    }

    function renderTable(data) {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        
        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No mechanics found.</td></tr>';
            return;
        }

        data.forEach(mgr => {
            const tr = document.createElement('tr');
            
            const joinDate = mgr.created_at ? new Date(mgr.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : 'N/A';
            const nameParts = mgr.name ? mgr.name.split(' ') : ['U'];
            const initials = nameParts.map(n => n[0]).join('').substring(0, 2).toUpperCase();
            
            // Random color class based on id to look dynamic like original design
            const avatarClasses = ['sj', 'dp', ''];
            const randomAvatarClass = avatarClasses[mgr.id % 3];
            
            tr.innerHTML = `
                <td class="applicant-cell">
                    <div class="avatar-placeholder ${randomAvatarClass}">${initials}</div>
                    <div>
                        <span class="name">${mgr.name}</span>
                        <span class="email">${mgr.email}</span>
                    </div>
                </td>
                <td>${mgr.specialization || 'General'}</td>
                <td>${mgr.experience || 0} Years</td>
                <td>${mgr.workshop_name || 'Unassigned'}</td>
                <td>${joinDate}</td>
                <td>
                    <div class="status-btn">
                        <select class="status update-status-dropdown" data-id="${mgr.id}" data-name="${mgr.name}" data-workshop="${mgr.workshop_name || 'Unassigned'}">
                            <option value="Active" ${mgr.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Pending" ${mgr.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Suspended" ${mgr.status === 'Suspended' ? 'selected' : ''}>Suspended</option>
                        </select>
                    </div>
                </td>
            `;
            tableBody.appendChild(tr);
        });

        // Attach event listeners to status dropdowns
        document.querySelectorAll('.update-status-dropdown').forEach(select => {
            select.addEventListener('change', (e) => {
                const id = e.target.getAttribute('data-id');
                const name = e.target.getAttribute('data-name');
                const workshop = e.target.getAttribute('data-workshop');
                const newStatus = e.target.value;
                
                const formData = new FormData();
                formData.append('action', 'update_status');
                formData.append('id', id);
                formData.append('status', newStatus);
                
                fetch(API_URL, {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(resData => {
                    if(resData.success) {
                        alert(`Mechanic Name: ${name}\nWorkshop: ${workshop}\nUpdated Status: ${newStatus}`);
                        // No need to refetch if we just updated visual select, it stays in sync.
                        // But we can refetch to ensure sorting/filtering applies immediately if needed.
                        fetchMechanics(currentPage); 
                    } else {
                        alert("Error updating status: " + resData.error);
                    }
                })
                .catch(err => console.error("Error updating status", err));
            });
        });
    }

    function renderPagination(total, page, limit) {
        const totalPages = Math.ceil(total / limit);
        const start = (page - 1) * limit + 1;
        const end = Math.min(start + limit - 1, total);
        
        if (tableInfo) {
            tableInfo.innerText = `Showing ${total > 0 ? start : 0}-${end} of ${total} pending approvals`;
        }
        
        if (paginationNumbers) paginationNumbers.innerHTML = '';

        if (prevBtn) {
            prevBtn.disabled = page === 1;
            prevBtn.onclick = () => fetchMechanics(page - 1);
        }
        
        if (nextBtn) {
            nextBtn.disabled = page >= totalPages || total === 0;
            nextBtn.onclick = () => fetchMechanics(page + 1);
        }

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const span = document.createElement('span');
            span.className = `page-num ${i === page ? 'active' : ''}`;
            span.innerText = i;
            span.onclick = () => fetchMechanics(i);
            paginationNumbers.appendChild(span);
        }
    }

    function openAddModal() {
        if (!modal) return;
        form.reset();
        dateInput.value = new Date().toISOString().split('T')[0];
        modal.classList.add('active');
    }

    function closeModalHandler() {
        if (!modal) return;
        modal.classList.remove('active');
    }

    if (addBtn) addBtn.addEventListener('click', openAddModal);
    if (closeBtn) closeBtn.addEventListener('click', closeModalHandler);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModalHandler);

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const formData = new FormData(form);
            formData.append('action', 'add');

            fetch(API_URL, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModalHandler();
                    fetchMechanics(1);
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => console.error("Form submit error:", err));
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', () => fetchMechanics(1));
    }

    if (sortBtn) {
        sortBtn.addEventListener('click', () => {
            isSorted = !isSorted;
            sortBtn.style.backgroundColor = isSorted ? '#e2e8f0' : '';
            fetchMechanics(1);
        });
    }

    // Initialize
    fetchWorkshops();
    fetchMechanics(1);
}
