/**
 * service-offers.js
 */

document.addEventListener("DOMContentLoaded", () => {
    initServiceOffers();
});

function initServiceOffers() {
    const API_URL = '../../api/admin-api/service-offers-api.php';

    const tbody = document.getElementById('offers-tbody');
    const statusTabs = document.getElementById('status-tabs').querySelectorAll('.tab-btn');
    const sortSelect = document.getElementById('sort-select');
    const createOfferBtn = document.getElementById('create-offer-btn');
    
    const offerModal = document.getElementById('offer-modal');
    const modalTitle = document.getElementById('modal-title');
    const offerForm = document.getElementById('offer-form');
    const closeBtn = document.getElementById('close-modal-btn');
    const cancelBtn = document.getElementById('cancel-modal-btn');
    const submitBtn = document.getElementById('submit-offer-btn');

    let currentStatus = 'All Offers';
    let currentSort = 'newest';
    let allOffersData = [];

    // Tabs
    statusTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            statusTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            currentStatus = tab.getAttribute('data-status');
            fetchOffers();
        });
    });

    // Sort
    if (sortSelect) {
        sortSelect.addEventListener('change', () => {
            currentSort = sortSelect.value;
            fetchOffers();
        });
    }

    function fetchOffers() {
        const url = `${API_URL}?action=list&status=${encodeURIComponent(currentStatus)}&sort=${encodeURIComponent(currentSort)}`;
        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allOffersData = data.data;
                    renderTable(data.data);
                } else alert(data.error);
            })
            .catch(console.error);
    }

    function renderTable(offers) {
        if (!tbody) return;
        tbody.innerHTML = '';
        
        if (offers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No offers found.</td></tr>';
            return;
        }

        offers.forEach(offer => {
            let badgeClass = 'active';
            if (offer.status === 'Scheduled') badgeClass = 'scheduled';
            if (offer.status === 'Expired') badgeClass = 'expired';

            // Format dates
            const startDateStr = new Date(offer.start_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });
            const expiryDateStr = new Date(offer.expiry_date).toLocaleDateString('en-US', { month: 'short', day: '2-digit', year: 'numeric' });

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-semibold">${offer.title}</td>
                <td>${offer.audience}</td>
                <td>${startDateStr}</td>
                <td>${expiryDateStr}</td>
                <td><span class="status-badge ${badgeClass}">${offer.status}</span></td>
                <td class="text-right">
                    <div class="action-icons">
                        <button class="action-icon-btn edit-btn" data-id="${offer.id}"><i class="fa-solid fa-pen"></i></button>
                        <button class="action-icon-btn delete delete-btn" data-id="${offer.id}"><i class="fa-regular fa-trash-can"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });

        // Bind Actions
        document.querySelectorAll('.edit-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                const offer = allOffersData.find(o => o.id == id);
                if (offer) openModal('edit', offer);
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const id = e.currentTarget.getAttribute('data-id');
                if (confirm('Are you sure you want to delete this offer?')) {
                    deleteOffer(id);
                }
            });
        });
    }

    function deleteOffer(id) {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        fetch(API_URL, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if(data.success) fetchOffers();
                else alert(data.error);
            }).catch(console.error);
    }

    function openModal(mode, offer = null) {
        offerForm.reset();
        if (mode === 'add') {
            modalTitle.innerText = 'Add New Offer';
            document.getElementById('offer-action').value = 'add';
            document.getElementById('offer-id').value = '';
            submitBtn.innerText = 'Save';
        } else if (mode === 'edit') {
            modalTitle.innerText = 'Update Offer';
            document.getElementById('offer-action').value = 'update';
            document.getElementById('offer-id').value = offer.id;
            
            document.getElementById('offer-title').value = offer.title;
            document.getElementById('offer-desc').value = offer.description;
            document.getElementById('offer-audience').value = offer.audience;
            document.getElementById('offer-start').value = offer.start_date;
            document.getElementById('offer-expiry').value = offer.expiry_date;
            submitBtn.innerText = 'Update';
        }
        offerModal.classList.add('active');
    }

    function closeModal() {
        offerModal.classList.remove('active');
    }

    if (createOfferBtn) createOfferBtn.addEventListener('click', () => openModal('add'));
    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

    offerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(offerForm);
        
        fetch(API_URL, { method: 'POST', body: fd })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    fetchOffers();
                } else alert(data.error);
            }).catch(console.error);
    });

    fetchOffers();
}
