/**
 * service-offers.js
 * Specific interactions and dynamic functionality for the service-offers page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("service-offers page loaded successfully.");
    initSpecificInteractions();
    initOfferModal();
    initTableActions();
    loadOffersFromDB();
});

async function loadOffersFromDB() {
    const tbody = document.getElementById('offers-tbody');
    if (!tbody || !window.adminApi) return;

    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading data...</td></tr>';
    const offers = await window.adminApi.request('service-offers.php', 'GET');

    if (offers && offers.length > 0) {
        tbody.innerHTML = '';
        offers.forEach(o => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><span class="offer-title">${o.title}</span></td>
                <td>${o.description}</td>
                <td><span class="discount-badge">${o.discount_percentage}% OFF</span></td>
                <td>All Customers</td>
                <td>${new Date(o.valid_until).toLocaleDateString()}</td>
                <td>${calculateStatus(o.created_at, o.valid_until)}</td>
                <td>
                    <div class="action-btns">
                        <button class="btn-update" title="Update">Update</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
        initTableActions();
    } else {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No offers found.</td></tr>';
    }
}

function initSpecificInteractions() {
    // Handle specific filters
    const selects = document.querySelectorAll('select');
    selects.forEach(select => {
        select.addEventListener('change', (e) => {
            if (typeof showToast === 'function') {
                showToast("Data filtered by: " + e.target.options[e.target.selectedIndex].text);
            }
        });
    });
}

function calculateStatus(startDateStr, expiryDateStr) {
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const startDate = new Date(startDateStr);
    const expiryDate = new Date(expiryDateStr);

    if (startDate > today) {
        return `<span class="status-badge scheduled">Scheduled</span>`;
    } else if (expiryDate < today) {
        return `<span class="status-badge expired">Expired</span>`;
    } else {
        return `<span class="status-badge active">Active</span>`;
    }
}

function formatDateToShort(dateStr) {
    const options = { year: 'numeric', month: 'short', day: '2-digit' };
    return new Date(dateStr).toLocaleDateString('en-US', options);
}

function initOfferModal() {
    const addBtn = document.getElementById('add-offer-btn');
    const modal = document.getElementById('offer-modal');
    const closeBtn = document.getElementById('close-offer-modal-btn');
    const cancelBtn = document.getElementById('cancel-offer-modal-btn');
    const form = document.getElementById('offer-form');

    if (!modal || !form) return;

    const titleEl = document.getElementById('offer-title');
    const discountEl = document.getElementById('offer-discount');
    const audienceEl = document.getElementById('offer-audience');
    const startDateEl = document.getElementById('offer-start-date');
    const expiryDateEl = document.getElementById('offer-expiry-date');
    const modalTitle = document.getElementById('modal-title');
    const submitBtn = document.getElementById('submit-offer-btn');

    let currentEditingRow = null;

    const closeModal = () => {
        modal.classList.remove('active');
        form.reset();
        currentEditingRow = null;
    };

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    if (addBtn) {
        addBtn.addEventListener('click', () => {
            modalTitle.innerText = "Add Offer";
            submitBtn.innerText = "Create Event";
            discountEl.value = '';

            // Auto-fill start date with today
            const today = new Date().toISOString().split('T')[0];
            startDateEl.value = today;
            // startDateEl.readOnly = true; // Optional based on requirements

            modal.classList.add('active');
        });
    }

    form.addEventListener('submit', (e) => {
        e.preventDefault();

        const title = titleEl.value;
        const discount = discountEl.value;
        const audience = audienceEl.value;
        const startDate = startDateEl.value;
        const expiryDate = expiryDateEl.value;
        const statusHtml = calculateStatus(startDate, expiryDate);

        const shortStart = formatDateToShort(startDate);
        const shortExpiry = formatDateToShort(expiryDate);

        if (currentEditingRow) {
            // Update existing row
            currentEditingRow.cells[0].innerHTML = `<span class="offer-title">${title}</span>`;
            currentEditingRow.cells[2].innerHTML = `<span class="discount-badge">${discount}% OFF</span>`;
            currentEditingRow.cells[3].innerText = audience;
            currentEditingRow.cells[4].innerText = shortExpiry;
            currentEditingRow.cells[4].dataset.iso = expiryDate;
            currentEditingRow.cells[5].innerHTML = statusHtml;

            if (typeof showToast === 'function') {
                showToast("Offer updated successfully!");
            }
        } else {
            if (typeof showToast === 'function') {
                showToast("Saving offer to database...", "info");
            }
            if (window.adminApi) {
                window.adminApi.request('service-offers.php', 'POST', {
                    title, description: 'Created from admin panel', discount_percentage: discount, valid_until: expiryDate, status: 'Active'
                }).then(() => {
                    loadOffersFromDB();
                    if (typeof showToast === 'function') {
                        showToast("Offer added successfully!");
                    }
                });
            }
        }

        closeModal();
    });

    // Expose edit function to global scope to allow table delegated events to trigger it
    window.openEditOfferModal = function (row) {
        currentEditingRow = row;
        modalTitle.innerText = "Edit Offer";
        submitBtn.innerText = "Save Offer";

        titleEl.value = row.cells[0].innerText.trim();
        discountEl.value = parseInt(row.cells[2].innerText) || 0;
        audienceEl.value = row.cells[3].innerText.trim();

        // Try to get ISO from dataset, else parse short date
        let startIso = row.cells[4].dataset.iso;
        if (!startIso) {
            // We use expiry date as placeholder for start date since start date isn't rendered
            startIso = new Date(row.cells[4].innerText).toISOString().split('T')[0];
        }
        let expiryIso = row.cells[4].dataset.iso;
        if (!expiryIso) {
            expiryIso = new Date(row.cells[4].innerText).toISOString().split('T')[0];
        }

        startDateEl.value = startIso;
        expiryDateEl.value = expiryIso;

        modal.classList.add('active');
    };
}

function initTableActions() {
    const tbody = document.getElementById('offers-tbody');
    if (!tbody) return;

    tbody.addEventListener('click', (e) => {
        const updateBtn = e.target.closest('.btn-update');
        if (updateBtn) {
            if (window.openEditOfferModal) {
                window.openEditOfferModal(updateBtn.closest('tr'));
            }
            return;
        }

        const btn = e.target.closest('.action-icon-btn');
        if (!btn) return;

        const row = btn.closest('tr');

        if (btn.classList.contains('delete') || btn.classList.contains('delete-btn')) {
            // Task 2: Delete
            if (confirm("Are you sure you want to delete this offer?")) {
                row.remove();
                if (typeof showToast === 'function') {
                    showToast("Offer deleted successfully!");
                }
            }
        } else if (btn.classList.contains('edit-btn') || btn.querySelector('.fa-pen')) {
            // Task 2: Edit
            if (window.openEditOfferModal) {
                window.openEditOfferModal(row);
            }
        }
    });
}
