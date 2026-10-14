/**
 * service-categories.js
 * Dynamic interactions for Service Categories Page
 */

document.addEventListener("DOMContentLoaded", () => {
    initCategoriesFunctionality();
});

function initCategoriesFunctionality() {
    let currentData = [];

    const cardsGrid = document.getElementById('cards-grid');
    const filterStatus = document.getElementById('filter-status');
    const addCategoryBtn = document.getElementById('add-category-btn');

    // Modals
    const modal = document.getElementById('category-modal');
    const closeBtn = document.getElementById('close-modal');
    const cancelBtn = document.getElementById('cancel-modal');
    const form = document.getElementById('category-form');
    const modalTitle = document.getElementById('modal-title');
    const saveModalBtn = document.getElementById('save-modal-btn');
    
    // Form Inputs
    const catId = document.getElementById('category-id');
    const catName = document.getElementById('category-name');
    const catDesc = document.getElementById('category-description');
    const catPrice = document.getElementById('category-price');
    const catStatus = document.getElementById('category-status');

    const API_URL = '../../api/admin-api/service-categories-api.php';

    function fetchCategories() {
        const status = filterStatus ? filterStatus.value : 'All';
        let url = `${API_URL}?action=list&status=${encodeURIComponent(status)}`;

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    currentData = data.data;
                    renderGrid(data.data);
                }
            })
            .catch(err => console.error("Error fetching categories:", err));
    }

    function renderGrid(data) {
        if (!cardsGrid) return;
        cardsGrid.innerHTML = '';
        
        data.forEach(cat => {
            const card = document.createElement('div');
            card.className = 'service-card';
            card.style.cursor = 'pointer'; // Make it explicitly clickable
            
            // Random icons/colors for variety based on id
            const iconClasses = ['fa-car-battery', 'fa-bolt', 'fa-paint-roller', 'fa-wrench', 'fa-oil-can'];
            const icon = iconClasses[cat.id % iconClasses.length];
            const isRed = cat.id % 2 === 0;
            const tintClass = isRed ? 'red-tint' : 'blue-tint';
            
            // Badge color mapping
            let badgeClass = 'review';
            let badgeText = cat.status.toUpperCase();
            if (cat.status === 'Active') badgeClass = 'active';
            if (cat.status === 'Suspended') badgeClass = 'suspended'; // Custom if defined, else fallback to review css
            
            card.innerHTML = `
                <div class="card-top">
                    <div class="service-icon-box ${tintClass}">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                    <button class="badge ${badgeClass}">${badgeText}</button>
                </div>
                <div class="card-body">
                    <h3>${cat.name}</h3>
                    <p>${cat.description}</p>
                </div>
                <div class="card-footer">
                    <div class="price-info">
                        <span class="price-label">BASE PRICE</span>
                        <span class="price-value">&#2547;${parseFloat(cat.base_price).toLocaleString()}</span>
                    </div>
                </div>
            `;
            
            // Attach click event for updating
            card.addEventListener('click', () => openUpdateModal(cat));
            
            cardsGrid.appendChild(card);
        });

        // Always append Create New Category card at the end
        const createCard = document.createElement('div');
        createCard.className = 'service-card dashed-card';
        createCard.innerHTML = `
            <div class="dashed-content">
                <div class="add-circle-icon">
                    <i class="fa-solid fa-plus"></i>
                </div>
                <h3>Create New Category</h3>
                <p>Define a new service grouping for your workshop.</p>
            </div>
        `;
        createCard.addEventListener('click', openAddModal);
        cardsGrid.appendChild(createCard);
    }

    function openModal() {
        if(modal) modal.classList.add('active');
    }

    function closeModal() {
        if(modal) modal.classList.remove('active');
        if(form) form.reset();
    }

    function openAddModal() {
        modalTitle.innerText = "Add New Category";
        saveModalBtn.innerText = "Save";
        catId.value = '';
        openModal();
    }

    function openUpdateModal(cat) {
        modalTitle.innerText = "View Category Details";
        saveModalBtn.innerText = "Update";
        catId.value = cat.id;
        catName.value = cat.name;
        catDesc.value = cat.description;
        catPrice.value = cat.base_price;
        catStatus.value = cat.status;
        
        openModal();
    }

    if (addCategoryBtn) addCategoryBtn.addEventListener('click', openAddModal);
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
                    fetchCategories();
                } else {
                    alert("Error: " + data.error);
                }
            })
            .catch(err => console.error("Form submit error:", err));
        });
    }

    if (filterStatus) {
        filterStatus.addEventListener('change', () => fetchCategories());
    }

    // Initial load
    fetchCategories();
}
