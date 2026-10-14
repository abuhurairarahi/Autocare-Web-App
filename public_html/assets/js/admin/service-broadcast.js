/**
 * service-broadcast.js
 */

document.addEventListener("DOMContentLoaded", () => {
    initServiceBroadcast();
});

function initServiceBroadcast() {
    const API_URL = '../../api/admin-api/service-broadcast-api.php';

    // Table elements
    const tbody = document.getElementById('notices-tbody');
    const statusFilter = document.getElementById('status-filter');
    const tableInfo = document.getElementById('table-info');
    const paginationNumbers = document.getElementById('pagination-numbers');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    // Form elements
    const createForm = document.getElementById('create-notice-form');
    const titleInput = document.getElementById('notice-title');
    const typeSelect = document.getElementById('notice-type');
    const prioritySelect = document.getElementById('notice-priority');
    const audienceSelect = document.getElementById('notice-audience');
    const contentInput = document.getElementById('notice-content');
    const scheduleCheckbox = document.getElementById('schedule-later');
    
    const draftBtn = document.getElementById('btn-draft');
    const publishBtn = document.getElementById('btn-publish');

    // Modal elements
    const modal = document.getElementById('notice-modal');
    const editIdInput = document.getElementById('edit-notice-id');
    const editTitleInput = document.getElementById('edit-notice-title');
    const editContentInput = document.getElementById('edit-notice-content');
    const modalCloseBtn = document.getElementById('modal-close-btn');
    const modalCloseIcon = document.getElementById('close-modal-btn');
    const modalPublishBtn = document.getElementById('modal-publish-btn');

    let currentPage = 1;
    let totalPages = 1;
    let currentFilter = 'All';
    let allNotices = [];

    // Filter listener
    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            currentFilter = statusFilter.value;
            currentPage = 1;
            fetchNotices();
        });
    }

    // Pagination Listeners
    if (prevBtn) prevBtn.addEventListener('click', () => {
        if (currentPage > 1) { currentPage--; fetchNotices(); }
    });
    if (nextBtn) nextBtn.addEventListener('click', () => {
        if (currentPage < totalPages) { currentPage++; fetchNotices(); }
    });

    function fetchNotices() {
        const url = `${API_URL}?action=list&status=${encodeURIComponent(currentFilter)}&page=${currentPage}&_t=${new Date().getTime()}`;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allNotices = data.data;
                    renderTable(data.data);
                    renderPagination(data.total, data.page, data.limit);
                } else alert(data.error);
            })
            .catch(console.error);
    }

    function renderTable(notices) {
        if (!tbody) return;
        tbody.innerHTML = '';
        if (notices.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No notices found.</td></tr>';
            return;
        }

        notices.forEach(notice => {
            // Priority
            let pBadge = 'badge-low';
            if (notice.priority === 'High') pBadge = 'badge-high';
            else if (notice.priority === 'Medium') pBadge = 'badge-medium';

            // Type
            let tClass = 'type-announcement';
            if (notice.type === 'Maintenance') tClass = 'type-maintenance';
            else if (notice.type === 'Update') tClass = 'type-update';
            else if (notice.type === 'Alert') tClass = 'type-alert';

            // Status
            let sClass = 'status-draft';
            if (notice.status === 'Published') sClass = 'status-published';
            else if (notice.status === 'Scheduled') sClass = 'status-scheduled';
            else if (notice.status === 'Completed') sClass = 'status-completed';

            const tr = document.createElement('tr');
            tr.style.cursor = (notice.status === 'Draft' || notice.status === 'Scheduled') ? 'pointer' : 'default';
            tr.innerHTML = `
                <td><span class="notice-title">${notice.title}</span></td>
                <td><span class="type-tag ${tClass}">${notice.type}</span></td>
                <td>${notice.audience}</td>
                <td><span class="badge ${pBadge}">${notice.priority}</span></td>
                <td><div class="status-select ${sClass}">${notice.status}</div></td>
            `;
            
            // Row Click
            if (notice.status === 'Draft' || notice.status === 'Scheduled') {
                tr.addEventListener('click', () => {
                    openModal(notice);
                });
            }

            tbody.appendChild(tr);
        });
    }

    function renderPagination(total, page, limit) {
        totalPages = Math.ceil(total / limit) || 1;
        
        let start = ((page - 1) * limit) + 1;
        let end = Math.min(page * limit, total);
        if (total === 0) { start = 0; end = 0; }
        
        if (tableInfo) tableInfo.innerText = `Showing ${start} to ${end} of ${total} notices`;

        if (paginationNumbers) {
            paginationNumbers.innerHTML = '';
            for (let i = 1; i <= totalPages; i++) {
                const btn = document.createElement('button');
                btn.className = 'page-btn' + (i === page ? ' active' : '');
                btn.innerText = i;
                btn.addEventListener('click', () => {
                    currentPage = i;
                    fetchNotices();
                });
                paginationNumbers.appendChild(btn);
            }
        }
    }

    // Creating Notice
    function submitNotice(status) {
        if (!titleInput.value || !contentInput.value) {
            alert("Title and Content are required!");
            return;
        }
        
        const fd = new FormData();
        fd.append('action', 'add');
        fd.append('title', titleInput.value);
        fd.append('type', typeSelect.value);
        fd.append('priority', prioritySelect.value);
        fd.append('audience', audienceSelect.value);
        fd.append('content', contentInput.value);
        fd.append('status', status);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    createForm.reset();
                    fetchNotices();
                } else alert(data.error);
            })
            .catch(console.error);
    }

    if (draftBtn) draftBtn.addEventListener('click', () => submitNotice('Draft'));
    if (publishBtn) publishBtn.addEventListener('click', () => {
        if (scheduleCheckbox && scheduleCheckbox.checked) {
            submitNotice('Scheduled');
        } else {
            submitNotice('Published');
        }
    });

    // Modal
    function openModal(notice) {
        editIdInput.value = notice.id;
        editTitleInput.value = notice.title;
        editContentInput.value = notice.content;
        modal.classList.add('active');
    }

    function closeModal() {
        modal.classList.remove('active');
    }

    if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
    if (modalCloseIcon) modalCloseIcon.addEventListener('click', closeModal);

    if (modalPublishBtn) modalPublishBtn.addEventListener('click', () => {
        const id = editIdInput.value;
        const notice = allNotices.find(n => n.id == id);
        if (!notice) return;

        const fd = new FormData();
        fd.append('action', 'update');
        fd.append('id', id);
        fd.append('title', notice.title);
        fd.append('type', notice.type);
        fd.append('priority', notice.priority);
        fd.append('audience', notice.audience);
        fd.append('content', notice.content);
        fd.append('status', 'Published'); // Force publish

        fetch(API_URL, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    closeModal();
                    fetchNotices();
                } else alert(data.error);
            }).catch(console.error);
    });

    // Initial Fetch
    fetchNotices();
}
