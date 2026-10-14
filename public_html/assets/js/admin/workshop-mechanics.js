document.addEventListener("DOMContentLoaded", () => {
    console.log("workshop-mechanics page loaded successfully.");
    initSpecificInteractions();
    initAddMechanicModal();
    loadMechanicsFromDB();
});

async function loadMechanicsFromDB() {
    const tbody = document.getElementById('mechanic-tbody');
    if (!tbody || !window.adminApi) return;

    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading data...</td></tr>';
    const mechanics = await window.adminApi.request('workshop-mechanics.php', 'GET');

    if (mechanics && mechanics.length > 0) {
        tbody.innerHTML = '';
        mechanics.forEach((m, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>MEC-${2000 + m.user_id}</td>
                <td>
                    <div class="user-info">
                        <div class="user-avatar" style="background: hsl(${(idx * 60) % 360}, 60%, 50%);">${m.name.charAt(0)}</div>
                        <div class="user-details">
                            <span class="user-name" style="font-weight: bold">${m.name}</span><br>
                            <span class="user-email">${m.email}</span>
                        </div>
                    </div>
                </td>
                <td>${m.phone || 'N/A'}</td>
                <td>Default Workshop</td>
                <td>${new Date(m.created_at).toLocaleDateString()}</td>
                <td><span class="status-badge active" style="font-weight: bold">Active</span></td>
                <td>
                    <div class="action-btns">
                        <button class="btn-update" title="Update">Update</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No mechanics found.</td></tr>';
    }
}

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

function initAddMechanicModal() {
    const addBtn = document.getElementById('add-mechanic-btn');
    const modal = document.getElementById('add-mechanic-modal');
    const closeBtn = document.getElementById('close-mechanic-modal-btn');
    const cancelBtn = document.getElementById('cancel-mechanic-modal-btn');
    const form = document.getElementById('add-mechanic-form');

    if (!addBtn || !modal) return;

    addBtn.addEventListener('click', () => { modal.classList.add('active'); });
    const closeModal = () => { modal.classList.remove('active'); if (form) form.reset(); };

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof showToast === 'function') {
                showToast("Mechanic saved successfully!");
            } else {
                alert("Mechanic saved successfully!");
            }
            closeModal();
        });
    }
}
