/**
 * service-broadcast.js
 * Specific interactions and dynamic functionality for the service-broadcast page.
 */

document.addEventListener("DOMContentLoaded", async () => {
    console.log("service-broadcast page loaded successfully.");
    initSpecificInteractions();
    initNoticeForm();
    initTableInteractionsAndModal();
    await loadBroadcastsFromDB();
});

async function loadBroadcastsFromDB() {
    const tbody = document.getElementById('notice-tbody');
    if (!tbody || !window.adminApi) return;
    
    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading data...</td></tr>';
    const broadcasts = await window.adminApi.request('service-broadcast.php', 'GET');
    
    if (broadcasts && broadcasts.length > 0) {
        tbody.innerHTML = '';
        broadcasts.forEach(b => {
            // Reusing existing createNoticeRow logic
            const tr = createNoticeRow(b.title, 'Announcement', b.audience, b.priority, b.status);
            tbody.appendChild(tr);
        });
        initTableInteractionsAndModal(); // Re-bind click events for edit
    } else {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No broadcasts found.</td></tr>';
    }
}

function initSpecificInteractions() {
    // 4. Handle specific filters
    const selects = document.querySelectorAll('.page-header select, .topbar select');
    selects.forEach(select => {
        select.addEventListener('change', (e) => {
            if (typeof showToast === 'function') {
                showToast("Data filtered by: " + e.target.options[e.target.selectedIndex].text);
            }
        });
    });
}

function createNoticeRow(title, typeStr, audience, priority, status) {
    const typeClassMap = {
        'Maintenance': 'type-maintenance',
        'Update': 'type-update',
        'Alert': 'type-alert',
        'Announcement': 'type-announcement'
    };
    const typeClass = typeClassMap[typeStr] || 'type-announcement';

    const priorityClassMap = {
        'Low': 'badge-low',
        'Medium': 'badge-medium',
        'High': 'badge-high'
    };
    const priorityClass = priorityClassMap[priority] || 'badge-low';
    
    let statusClass = 'status-published';
    if(status === 'Draft') statusClass = 'status-draft';
    if(status === 'Scheduled') statusClass = 'status-scheduled';
    if(status === 'Completed') statusClass = 'status-completed';

    const tr = document.createElement('tr');
    if (status !== 'Published' && status !== 'Completed') {
        tr.style.cursor = "pointer";
    }
    tr.innerHTML = `
        <td><span class="notice-title">${title}</span></td>
        <td><span class="type-tag ${typeClass}">${typeStr}</span></td>
        <td>${audience}</td>
        <td><span class="badge ${priorityClass}">${priority}</span></td>
        <td>
            <div class="status-select ${statusClass}">${status}</div>
        </td>
    `;
    return tr;
}

function initNoticeForm() {
    const form = document.getElementById('notice-form');
    const btnDraft = document.getElementById('btn-draft');
    const tbody = document.getElementById('notice-tbody');
    
    if (!form || !tbody) return;

    let clickedDraft = false;

    if (btnDraft) {
        btnDraft.addEventListener('click', (e) => {
            clickedDraft = true;
            form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
        });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const titleInput = document.getElementById('notice-title');
        const typeSelect = document.getElementById('notice-type');
        const prioritySelect = document.getElementById('notice-priority');
        const audienceSelect = document.getElementById('notice-audience');
        const scheduleCheckbox = document.getElementById('schedule-later');

        if (!titleInput.value.trim()) {
            alert('Please enter a notice title.');
            clickedDraft = false;
            return;
        }

        const title = titleInput.value;
        const typeStr = typeSelect.value;
        const priority = prioritySelect.value;
        const audience = audienceSelect.value;
        const isScheduled = scheduleCheckbox.checked;

        let status = 'Published';
        if (clickedDraft) status = 'Draft';
        else if (isScheduled) status = 'Scheduled';

        if (typeof showToast === 'function') {
            showToast('Saving notice to database...', 'info');
        }

        // Save to DB via API
        if (window.adminApi) {
            window.adminApi.request('service-broadcast.php', 'POST', {
                title, content: 'Content generated from UI.', audience, priority, status
            }).then(() => {
                loadBroadcastsFromDB();
                if (typeof showToast === 'function') {
                    showToast("Notice " + status + " successfully!");
                }
            });
        }

        form.reset();
        clickedDraft = false;
    });
}

function initTableInteractionsAndModal() {
    const tbody = document.getElementById('notice-tbody');
    const modal = document.getElementById('edit-notice-modal');
    const closeBtn = document.getElementById('close-modal-btn');
    const form = document.getElementById('edit-notice-form');
    const btnDraft = document.getElementById('edit-btn-draft');

    if(!tbody || !modal) return;

    // make existing rows clickable if they are not Published/Completed
    Array.from(tbody.rows).forEach(row => {
        const statusText = row.cells[4].innerText.trim();
        if (statusText !== 'Published' && statusText !== 'Completed') {
            row.style.cursor = "pointer";
        } else {
            row.style.cursor = "default";
        }
    });

    let currentEditingRow = null;

    const closeModal = () => {
        modal.classList.remove('active');
        if(form) form.reset();
        currentEditingRow = null;
    };

    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if(e.target === modal) closeModal(); });

    tbody.addEventListener('click', (e) => {
        const row = e.target.closest('tr');
        if(!row) return;

        currentEditingRow = row;

        const title = row.cells[0].innerText.trim();
        const typeStr = row.cells[1].innerText.trim();
        const audience = row.cells[2].innerText.trim();
        const priority = row.cells[3].innerText.trim();
        const status = row.cells[4].innerText.trim();

        if (status === 'Published' || status === 'Completed') {
            if (typeof showToast === 'function') {
                showToast("This notice cannot be edited.");
            }
            return;
        }

        document.getElementById('edit-notice-title').value = title;
        document.getElementById('edit-notice-type').value = typeStr;
        document.getElementById('edit-notice-audience').value = audience;
        document.getElementById('edit-notice-priority').value = priority;
        
        document.getElementById('edit-schedule-later').checked = (status === 'Scheduled');

        modal.classList.add('active');
    });

    if(form) {
        let editClickedDraft = false;

        if (btnDraft) {
            btnDraft.addEventListener('click', (e) => {
                editClickedDraft = true;
                form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            });
        }

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const title = document.getElementById('edit-notice-title').value;
            const typeStr = document.getElementById('edit-notice-type').value;
            const priority = document.getElementById('edit-notice-priority').value;
            const audience = document.getElementById('edit-notice-audience').value;
            const isScheduled = document.getElementById('edit-schedule-later').checked;

            let status = 'Published';
            if (editClickedDraft) status = 'Draft';
            else if (isScheduled) status = 'Scheduled';

            if(currentEditingRow) {
                const newRow = createNoticeRow(title, typeStr, audience, priority, status);
                currentEditingRow.replaceWith(newRow);

                if (typeof showToast === 'function') {
                    showToast("Notice updated to " + status + "!");
                }
            }

            closeModal();
            editClickedDraft = false;
        });
    }
}
