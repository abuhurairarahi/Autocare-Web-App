/**
 * manager-cost-estimation.js
 * Comprehensive logic for Cost Estimates & Estimate Builder:
 * Estimate table rendering, builder panel population, line item management,
 * auto tax/subtotal calculation, draft saving, and customer sending.
 */

let activeEstimateId = 1;

document.addEventListener('DOMContentLoaded', () => {
  renderEstimatesTable();
  initEstimateBuilder();
  initNewEstimateButton();
});

window.addEventListener('autocare:store:synced', () => {
  renderEstimatesTable();
});

/**
 * 1. Render Estimates Table
 */
function renderEstimatesTable() {
  const tbody = document.querySelector('.table-panel table tbody');
  if (!tbody || typeof AutoCareStore === 'undefined') return;

  const estimates = AutoCareStore.getEstimates();

  let html = '';
  estimates.forEach(est => {
    const isSelected = est.id === activeEstimateId;
    let badgeClass = 'badge-draft';
    if (est.status === 'Sent') badgeClass = 'badge-sent';
    else if (est.status === 'Send to Customer') badgeClass = 'badge-send';

    html += `
      <tr class="${isSelected ? 'selected' : ''}" data-est-id="${est.id}" style="cursor: pointer;">
        <td class="bold">${est.code}</td>
        <td class="blue-link" onclick="event.stopPropagation(); window.location.href='manager-jobCards.html';" style="cursor: pointer;" title="View Job Card">${est.job_card_code || 'JC-' + est.job_card_id}</td>
        <td>${est.customer_name}</td>
        <td>${est.mechanic_name || '-'}</td>
        <td class="bold">৳${(est.total_estimated_cost || 0).toFixed(2)}</td>
        <td><span class="badge ${badgeClass}">${est.status}</span></td>
        <td>${est.sent_date || '-'}</td>
        <td class="action-cell">
          <button class="icon-btn" title="View / Edit Estimate">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;

  // Row selection
  tbody.querySelectorAll('tr').forEach(row => {
    row.addEventListener('click', () => {
      const id = Number(row.getAttribute('data-est-id'));
      if (id) {
        activeEstimateId = id;
        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('selected'));
        row.classList.add('selected');
        loadEstimateIntoBuilder(id);
      }
    });
  });

  // Load first selected into builder
  loadEstimateIntoBuilder(activeEstimateId);
}

/**
 * 2. Populate Estimate Builder Panel
 */
function loadEstimateIntoBuilder(estimateId) {
  const panel = document.querySelector('.builder-panel');
  if (!panel || typeof AutoCareStore === 'undefined') return;

  const est = AutoCareStore.getEstimateById(estimateId);
  if (!est) return;

  // Subtitle
  const subtitle = panel.querySelector('.builder-subtitle');
  if (subtitle) subtitle.innerText = `${est.code} (${est.status.toUpperCase()})`;

  // Mechanic Info
  const mechBanner = panel.querySelector('.info-banner span');
  if (mechBanner) mechBanner.innerText = `Initial Estimate by: ${est.mechanic_name || 'Workshop Manager'}`;

  // Linked Job Card
  const linkedCard = panel.querySelector('.linked-card-box span');
  const linkedBox = panel.querySelector('.linked-card-box');
  if (linkedCard) linkedCard.innerText = `${est.job_card_code || 'JC-' + est.job_card_id} - ${est.job_card_title || 'Service'}`;
  if (linkedBox) {
    linkedBox.style.cursor = 'pointer';
    linkedBox.title = 'Open Job Card';
    linkedBox.onclick = () => window.location.href = 'manager-jobCards.html';
  }

  // Render Line Items
  renderLineItems(est);
}

function renderLineItems(est) {
  const list = document.querySelector('.line-items-list');
  if (!list) return;

  if (!est.line_items || est.line_items.length === 0) {
    list.innerHTML = `<div style="text-align: center; color: #94a3b8; padding: 20px; font-size: 13px;">No items added yet. Click "+ Add Item" below.</div>`;
    updateTotalsDisplay(0, 0, 0);
    return;
  }

  let html = '';
  let subtotal = 0;

  est.line_items.forEach((item, index) => {
    const itemTotal = (item.hours_or_qty || 1) * (item.unit_price || 0);
    subtotal += itemTotal;

    html += `
      <div class="item-card" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; margin-bottom: 8px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px;">
        <div class="item-info">
          <h4 style="margin: 0 0 4px 0; font-size: 14px;">${item.description}</h4>
          <small style="color: #64748b;">${item.hours_or_qty} ${item.description.toLowerCase().includes('labor') ? 'hrs' : 'unit(s)'} @ ৳${item.unit_price}</small>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
          <div class="item-price" style="font-weight: 700; color: #0f172a;">৳${itemTotal.toFixed(2)}</div>
          <button onclick="removeLineItem(${est.id}, ${index})" style="background: none; border: none; color: #ef4444; font-size: 16px; cursor: pointer;" title="Remove Item">&times;</button>
        </div>
      </div>
    `;
  });

  list.innerHTML = html;

  const tax = subtotal * 0.085;
  const grandTotal = subtotal + tax;

  est.subtotal = subtotal;
  est.tax_amount = tax;
  est.total_estimated_cost = grandTotal;

  updateTotalsDisplay(subtotal, tax, grandTotal);
}

function updateTotalsDisplay(subtotal, tax, grandTotal) {
  const totalsSection = document.querySelector('.totals-section');
  if (!totalsSection) return;

  const subtotalEl = totalsSection.querySelectorAll('.total-row span')[1];
  const taxEl = totalsSection.querySelectorAll('.total-row span')[3];
  const grandEl = totalsSection.querySelector('.grand-price');

  if (subtotalEl) subtotalEl.innerText = `৳${subtotal.toFixed(2)}`;
  if (taxEl) taxEl.innerText = `৳${tax.toFixed(2)}`;
  if (grandEl) grandEl.innerText = `৳${grandTotal.toFixed(2)}`;
}

window.removeLineItem = function (estId, itemIndex) {
  const est = AutoCareStore.getEstimateById(estId);
  if (est && est.line_items) {
    est.line_items.splice(itemIndex, 1);
    AutoCareStore.saveEstimate(est);
    renderLineItems(est);
    renderEstimatesTable();
    showToast('Line item removed', 'info');
  }
};

/**
 * 3. Initialize Estimate Builder Form and Actions
 */
function initEstimateBuilder() {
  const addBtn = document.querySelector('.line-items-header .add-item-btn');
  const addFormBox = document.querySelector('.add-item-form');
  const cancelBtn = document.querySelector('.add-item-form .btn-cancel');
  const saveItemBtn = document.querySelector('.add-item-form .btn-save-item');
  const saveDraftBtn = document.querySelector('.builder-actions .btn-secondary');
  const sendCustomerBtn = document.querySelector('.builder-actions .btn-primary-send');
  const closeBtn = document.querySelector('.builder-header .close-btn');

  // Toggle Add Item Form
  if (addBtn && addFormBox) {
    addBtn.addEventListener('click', () => {
      addFormBox.style.display = addFormBox.style.display === 'none' ? 'block' : 'none';
      if (addFormBox.style.display === 'block') {
        const descInput = addFormBox.querySelector('input');
        if (descInput) descInput.focus();
      }
    });
  }

  // Cancel Add Item
  if (cancelBtn && addFormBox) {
    cancelBtn.addEventListener('click', (e) => {
      e.preventDefault();
      addFormBox.style.display = 'none';
    });
  }

  // Save Item
  if (saveItemBtn && addFormBox) {
    saveItemBtn.addEventListener('click', (e) => {
      e.preventDefault();
      const descInput = addFormBox.querySelector('input');
      const qtyInput = addFormBox.querySelectorAll('.form-row input')[0];
      const priceInput = addFormBox.querySelectorAll('.form-row input')[1];

      const desc = descInput ? descInput.value.trim() : '';
      const qty = parseFloat(qtyInput ? qtyInput.value : 1) || 1;
      const price = parseFloat(priceInput ? priceInput.value : 0) || 0;

      if (!desc) {
        showToast('Please enter an item description!', 'warning');
        return;
      }

      const est = AutoCareStore.getEstimateById(activeEstimateId);
      if (est) {
        if (!est.line_items) est.line_items = [];
        est.line_items.push({
          description: desc,
          hours_or_qty: qty,
          unit_price: price,
          total: qty * price
        });
        AutoCareStore.saveEstimate(est);
        renderLineItems(est);
        renderEstimatesTable();
        showToast('Line item added!', 'success');

        // Reset inputs
        if (descInput) descInput.value = '';
        if (qtyInput) qtyInput.value = '1';
        if (priceInput) priceInput.value = '0.00';
        addFormBox.style.display = 'none';
      }
    });
  }

  // Save Draft
  if (saveDraftBtn) {
    saveDraftBtn.addEventListener('click', () => {
      const est = AutoCareStore.getEstimateById(activeEstimateId);
      if (est) {
        est.status = 'Draft';
        AutoCareStore.saveEstimate(est);
        showToast(`Estimate ${est.code} saved as Draft!`, 'success');
        renderEstimatesTable();
      }
    });
  }

  // Send to Customer
  if (sendCustomerBtn) {
    sendCustomerBtn.addEventListener('click', () => {
      const est = AutoCareStore.getEstimateById(activeEstimateId);
      if (est) {
        est.status = 'Sent';
        est.sent_date = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        AutoCareStore.saveEstimate(est);
        showToast(`Estimate ${est.code} sent to customer for approval!`, 'success');
        renderEstimatesTable();
        setTimeout(() => {
          openModal({
            title: 'Estimate Sent to Customer',
            content: `<p>Estimate <strong>${est.code}</strong> for <strong>${est.customer_name}</strong> has been transmitted.</p><p>Would you like to message the customer in chat?</p>`,
            confirmText: 'Open Chat',
            cancelText: 'Stay on Estimates',
            onConfirm: () => {
              window.location.href = 'manager-chat.html';
            }
          });
        }, 300);
      }
    });
  }

  // Close Builder
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      showToast('Estimate builder closed', 'info');
    });
  }
}

/**
 * 4. "+ New Estimate" Button
 */
function initNewEstimateButton() {
  const newEstBtn = document.querySelector('.page-heading .btn-primary');
  if (!newEstBtn) return;

  newEstBtn.addEventListener('click', () => {
    const jobCards = AutoCareStore.getJobCards().filter(j => j.kanban_stage !== 'COMPLETED');
    const jcOptions = jobCards.map(c => `
      <option value="${c.id}">${c.code} - ${c.customer_name} (${c.vehicle_title || c.vehicle_details})</option>
    `).join('');

    openModal({
      title: 'Create New Repair Estimate',
      content: `
        <div>
          <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Select Job Card</label>
          <select id="modal-new-est-jc" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            ${jcOptions}
          </select>
        </div>
      `,
      confirmText: 'Initialize Estimate',
      onConfirm: () => {
        const jcId = document.getElementById('modal-new-est-jc').value;
        const jc = AutoCareStore.getJobCardById(jcId);
        if (jc) {
          const newEst = AutoCareStore.saveEstimate({
            job_card_id: jc.id,
            job_card_code: jc.code,
            job_card_title: jc.service_text || jc.vehicle_title,
            customer_name: jc.customer_name,
            mechanic_name: jc.mechanic_name,
            status: 'Draft',
            sent_date: '-',
            line_items: [
              { description: 'Initial Teardown & Diagnostic Labor', hours_or_qty: 2, unit_price: 150.00, total: 300.00 }
            ],
            subtotal: 300.00,
            tax_rate: 0.085,
            tax_amount: 25.50,
            total_estimated_cost: 325.50
          });

          activeEstimateId = newEst.id;
          renderEstimatesTable();
          showToast(`New estimate ${newEst.code} created!`, 'success');
        }
      }
    });
  });
}
