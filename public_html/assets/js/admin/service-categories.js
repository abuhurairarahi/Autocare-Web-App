/**
 * service-categories.js
 * Specific interactions and dynamic functionality for the service-categories page.
 */

document.addEventListener("DOMContentLoaded", () => {
    console.log("service-categories page loaded successfully.");
    initSpecificInteractions();
    initAddCategoryModal();
    loadCategoriesFromDB();
});

async function loadCategoriesFromDB() {
    const grid = document.querySelector('.cards-grid');
    if (!grid || !window.adminApi) return;
    
    grid.innerHTML = '<p style="text-align:center; width: 100%;">Loading categories...</p>';
    const categories = await window.adminApi.request('service-categories.php', 'GET');
    
    if (categories && categories.length > 0) {
        grid.innerHTML = '';
        categories.forEach(c => {
            const card = document.createElement('div');
            card.className = 'service-card';
            card.innerHTML = `
                <div class="card-top">
                    <div class="service-icon-box blue-tint">
                        <i class="fa-solid fa-wrench"></i>
                    </div>
                    <button class="badge active">ACTIVE</button>
                </div>
                <div class="card-body">
                    <h3>${c.name}</h3>
                    <p>${c.description}</p>
                </div>
                <div class="card-footer">
                    <div class="price-info">
                        <span class="price-label">EST. BASE PRICE</span>
                        <span class="price-value">&#2547;${c.base_price || '5,000'}</span>
                    </div>
                    <span class="item-count">0</span>
                </div>
            `;
            grid.appendChild(card);
        });
    } else {
        grid.innerHTML = '<p style="text-align:center; width: 100%;">No categories found.</p>';
    }
}

function initSpecificInteractions() {


    // 2. Wire up specific page buttons
    const primaryBtns = document.querySelectorAll('.primary-btn, .btn-primary');
    primaryBtns.forEach(btn => {
        // Only attach if it doesn't already have an action from global admin.js
        if(!btn.hasAttribute('data-wired')) {
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

function initAddCategoryModal() {
    const addBtns = [document.getElementById('add-category-btn'), document.getElementById('add-category-card-btn')];
    const modal = document.getElementById('add-category-modal');
    const closeBtn = document.getElementById('close-category-modal-btn');
    const cancelBtn = document.getElementById('cancel-category-modal-btn');
    const form = document.getElementById('add-category-form');

    if (!modal) return;

    addBtns.forEach(btn => {
        if(btn) btn.addEventListener('click', () => { modal.classList.add('active'); });
    });

    const closeModal = () => { modal.classList.remove('active'); if(form) form.reset(); };

    if(closeBtn) closeBtn.addEventListener('click', closeModal);
    if(cancelBtn) cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

    if(form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            if (typeof showToast === 'function') {
                showToast("Category added successfully!");
            } else {
                alert("Category added successfully!");
            }
            closeModal();
        });
    }
}
