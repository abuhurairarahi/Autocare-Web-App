/**
 * vehicle-owners.js
 * Dynamic interactions for Vehicle Owners Page
 */

document.addEventListener("DOMContentLoaded", () => {
    initOwnersFunctionality();
});

function initOwnersFunctionality() {
    let currentPage = 1;
    let isSorted = false;

    const tableBody = document.getElementById('owners-table-body');
    const filterStatus = document.getElementById('filter-status');
    const sortBtn = document.getElementById('sort-btn');
    const tableInfo = document.getElementById('table-info');

    // Pagination
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');
    const paginationNumbers = document.getElementById('pagination-numbers');

    const API_URL = '../../api/admin-api/vehicle-owners-api.php';

    function fetchOwners(page = 1) {
        currentPage = page;
        const status = filterStatus.value;
        
        let url = `${API_URL}?action=list&page=${page}`;
        if (status && status !== 'All') url += `&status=${encodeURIComponent(status)}`;
        if (isSorted) url += `&sort=true`; // Sorts by created_at ascending

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    renderTable(data.data);
                    renderPagination(data.total, data.page, data.limit);
                }
            })
            .catch(err => console.error("Error fetching vehicle owners:", err));
    }

    function renderTable(data) {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        
        if (data.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No vehicle owners found.</td></tr>';
            return;
        }

        data.forEach((owner, index) => {
            const tr = document.createElement('tr');
            
            const joinDate = owner.created_at ? new Date(owner.created_at).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }) : 'N/A';
            const nameParts = owner.name ? owner.name.split(' ') : ['U'];
            const initials = nameParts.map(n => n[0]).join('').substring(0, 2).toUpperCase();
            
            // Toggle colors based on index for variety
            const avatarClass = index % 2 === 0 ? 'blue-bg' : 'blue-light-bg';
            
            tr.innerHTML = `
                <td>
                    <div class="user-info">
                        <div class="avatar ${avatarClass}">${initials}</div>
                        <div class="user-details">
                            <span class="user-name">${owner.name}</span>
                            <span class="user-email">${owner.email}</span>
                        </div>
                    </div>
                </td>
                <td>${owner.phone || 'N/A'}</td>
                <td>${owner.vehicle_number || 'N/A'}</td>
                <td>${owner.vehicle_type || 'N/A'}</td>
                <td>${joinDate}</td>
                <td>
                    <div class="status-btn">
                        <select class="status update-status-dropdown" data-id="${owner.id}" data-name="${owner.name}" data-vehicle-number="${owner.vehicle_number || 'N/A'}" data-vehicle-type="${owner.vehicle_type || 'N/A'}">
                            <option value="Active" ${owner.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Pending" ${owner.status === 'Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Suspended" ${owner.status === 'Suspended' ? 'selected' : ''}>Suspended</option>
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
                const vehicleNumber = e.target.getAttribute('data-vehicle-number');
                const vehicleType = e.target.getAttribute('data-vehicle-type');
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
                        alert(`Vehicle Owner Name: ${name}\nVehicle Number: ${vehicleNumber}\nVehicle Type: ${vehicleType}\nUpdated Status: ${newStatus}`);
                        fetchOwners(currentPage); 
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
            prevBtn.onclick = () => fetchOwners(page - 1);
        }
        
        if (nextBtn) {
            nextBtn.disabled = page >= totalPages || total === 0;
            nextBtn.onclick = () => fetchOwners(page + 1);
        }

        if (totalPages <= 1) return;

        for (let i = 1; i <= totalPages; i++) {
            const btn = document.createElement('button');
            btn.className = `page-num ${i === page ? 'active' : ''}`;
            btn.innerText = i;
            btn.onclick = () => fetchOwners(i);
            paginationNumbers.appendChild(btn);
        }
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', () => fetchOwners(1));
    }

    if (sortBtn) {
        sortBtn.addEventListener('click', () => {
            isSorted = !isSorted;
            sortBtn.style.backgroundColor = isSorted ? '#e2e8f0' : '';
            fetchOwners(1);
        });
    }

    // Initialize
    fetchOwners(1);
}
