/**
 * manager-invoice-management.js
 * Invoices management for AutoCare Workshop Manager:
 * Dynamic revenue and pending counters, status filtering,
 * new invoice creation, mark as paid, printable invoice preview, and CSV export.
 */

let invoiceFilter = 'all';

document.addEventListener('DOMContentLoaded', () => {
  renderInvoiceStats();
  renderInvoicesTable();
  initInvoiceActions();
});

window.addEventListener('autocare:store:synced', () => {
  renderInvoiceStats();
  renderInvoicesTable();
});

/**
 * 1. Render Invoice Stats Cards
 */
function renderInvoiceStats() {
  if (typeof AutoCareStore === 'undefined') return;

  const invoices = AutoCareStore.getInvoices();

  const paidInvoices = invoices.filter(i => i.status === 'Paid');
  const pendingInvoices = invoices.filter(i => i.status === 'Pending');
  const overdueInvoices = invoices.filter(i => i.status === 'Overdue');

  const totalRevenue = paidInvoices.reduce((sum, i) => sum + (i.total_amount || 0), 0);
  const pendingValue = pendingInvoices.reduce((sum, i) => sum + (i.total_amount || 0), 0);
  const overdueValue = overdueInvoices.reduce((sum, i) => sum + (i.total_amount || 0), 0);

  const statCards = document.querySelectorAll('.stats-grid .stat-card');
  if (statCards.length >= 3) {
    // Card 1: Total Revenue
    statCards[0].querySelector('.stat-value').innerText = `৳${totalRevenue.toLocaleString()}`;

    // Card 2: Pending Invoices
    statCards[1].querySelector('.stat-value').innerText = pendingInvoices.length;
    statCards[1].querySelector('.stat-subtext').innerText = `Value: ৳${pendingValue.toLocaleString()}`;

    // Card 3: Overdue
    statCards[2].querySelector('.stat-value').innerText = overdueInvoices.length;
    statCards[2].querySelector('.stat-subtext').innerText = `Value: ৳${overdueValue.toLocaleString()}`;
  }
}

/**
 * 2. Render Invoices Table
 */
function renderInvoicesTable() {
  const tbody = document.querySelector('.table-card table tbody');
  const showingText = document.querySelector('.showing-text');
  if (!tbody || typeof AutoCareStore === 'undefined') return;

  let invoices = AutoCareStore.getInvoices();

  // Filter
  if (invoiceFilter !== 'all') {
    invoices = invoices.filter(i => i.status.toLowerCase() === invoiceFilter.toLowerCase());
  }

  if (showingText) {
    showingText.innerText = `Showing 1-${invoices.length} of ${invoices.length}`;
  }

  if (invoices.length === 0) {
    tbody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 30px; color: #94a3b8;">No invoices found matching current filter</td></tr>`;
    return;
  }

  let html = '';
  invoices.forEach(inv => {
    let badgeClass = 'badge-pending';
    if (inv.status === 'Paid') badgeClass = 'badge-paid';
    else if (inv.status === 'Overdue') badgeClass = 'badge-overdue';

    html += `
      <tr data-invoice-id="${inv.id}">
        <td class="bold"><a href="manager-jobCards.html" style="color: #2563eb; text-decoration: none; font-weight: 700;" title="View Job Card">${inv.invoice_number}</a></td>
        <td>
          <div class="customer-name">${inv.customer_name}</div>
          <div class="sub-email">${inv.customer_email || 'customer@gmail.com'}</div>
        </td>
        <td>
          <div class="vehicle-name">${inv.vehicle_name}</div>
          <div class="sub-vin">VIN: ${inv.vin || 'Pending'}</div>
        </td>
        <td>${inv.date}</td>
        <td class="bold">৳${(inv.total_amount || 0).toLocaleString()}</td>
        <td><span class="badge ${badgeClass}">${inv.status}</span></td>
        <td class="action-cell">
          <button class="icon-btn" title="Actions" onclick="openInvoiceMenu(event, ${inv.id})">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5"></circle><circle cx="12" cy="12" r="1.5"></circle><circle cx="12" cy="19" r="1.5"></circle></svg>
          </button>
        </td>
      </tr>
    `;
  });

  tbody.innerHTML = html;
}

/**
 * 3. Invoice Menu Modal
 */
window.openInvoiceMenu = function (event, invoiceId) {
  event.stopPropagation();
  const inv = AutoCareStore.getInvoiceById(invoiceId);
  if (!inv) return;

  openModal({
    title: `Invoice #${inv.invoice_number}`,
    content: `
      <div style="font-size: 13.5px; line-height: 1.6;">
        <div style="padding: 12px; background: #f8fafc; border-radius: 8px; margin-bottom: 14px;">
          <div><strong>Customer:</strong> ${inv.customer_name}</div>
          <div><strong>Vehicle:</strong> ${inv.vehicle_name}</div>
          <div><strong>Total Due:</strong> <span style="font-weight: 700; color: #16a34a;">৳${inv.total_amount.toLocaleString()}</span></div>
          <div><strong>Current Status:</strong> <b>${inv.status}</b></div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 8px;">
          ${inv.status !== 'Paid' ? `
            <button id="btn-inv-mark-paid" style="padding: 10px; background: #10b981; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-align: left;">
              ✓ Mark as Paid
            </button>
          ` : ''}
          <button id="btn-inv-preview" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer; text-align: left;">
            🖨️ View & Print Official Invoice
          </button>
          <button id="btn-inv-jobcard" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer; text-align: left;">
            📋 View Associated Job Card
          </button>
          <button id="btn-inv-chat" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer; text-align: left;">
            💬 Message Customer in Chat
          </button>
          <button id="btn-inv-reminder" style="padding: 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer; text-align: left;">
            🔔 Send Customer Payment Reminder
          </button>
        </div>
      </div>
    `,
    confirmText: 'Done',
    cancelText: '',
    onConfirm: () => {}
  });

  setTimeout(() => {
    const markPaidBtn = document.getElementById('btn-inv-mark-paid');
    const previewBtn = document.getElementById('btn-inv-preview');
    const jobcardBtn = document.getElementById('btn-inv-jobcard');
    const chatBtn = document.getElementById('btn-inv-chat');
    const reminderBtn = document.getElementById('btn-inv-reminder');

    if (jobcardBtn) {
      jobcardBtn.addEventListener('click', () => {
        window.location.href = 'manager-jobCards.html';
      });
    }

    if (chatBtn) {
      chatBtn.addEventListener('click', () => {
        window.location.href = 'manager-chat.html';
      });
    }

    if (markPaidBtn) {
      markPaidBtn.addEventListener('click', () => {
        AutoCareStore.updateInvoiceStatus(inv.id, 'Paid');
        showToast(`Invoice #${inv.invoice_number} marked as Paid!`, 'success');
        renderInvoiceStats();
        renderInvoicesTable();
      });
    }

    if (previewBtn) {
      previewBtn.addEventListener('click', () => {
        openModal({
          title: `Print Preview: #${inv.invoice_number}`,
          content: `
            <div style="padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-family: monospace; font-size: 13px; background: #fff;">
              <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px;">
                <div>
                  <h3 style="margin: 0; color: #f97316;">AutoCare Workshop</h3>
                  <small>Automotive Engineering & Services</small>
                </div>
                <div style="text-align: right;">
                  <strong>${inv.invoice_number}</strong><br>
                  <span>Date: ${inv.date}</span>
                </div>
              </div>
              <div style="margin-bottom: 12px;">
                <strong>BILLED TO:</strong><br>
                ${inv.customer_name}<br>
                ${inv.vehicle_name} (${inv.vin || 'N/A'})
              </div>
              <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
                <thead>
                  <tr style="border-bottom: 1px solid #cbd5e1; text-align: left;">
                    <th>Item Description</th>
                    <th style="text-align: right;">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Parts & Labor Repair Services</td>
                    <td style="text-align: right;">৳${inv.total_amount.toLocaleString()}</td>
                  </tr>
                </tbody>
              </table>
              <div style="text-align: right; border-top: 1px solid #cbd5e1; padding-top: 8px; font-size: 15px; font-weight: 700;">
                TOTAL: ৳${inv.total_amount.toLocaleString()}
              </div>
            </div>
          `,
          confirmText: 'Print',
          cancelText: 'Close',
          onConfirm: () => {
            window.print();
          }
        });
      });
    }

    if (reminderBtn) {
      reminderBtn.addEventListener('click', () => {
        showToast(`Payment reminder dispatched to ${inv.customer_email || inv.customer_name}!`, 'info');
      });
    }
  }, 100);
};

/**
 * 4. Top Action Buttons (New Invoice, Export List, Filter)
 */
function initInvoiceActions() {
  const newInvBtn = document.querySelector('.heading-actions .btn-primary');
  const exportBtn = document.querySelector('.heading-actions .btn-secondary');
  const filterDropdown = document.querySelector('.table-toolbar .filter-dropdown');

  // Filter dropdown click
  if (filterDropdown) {
    filterDropdown.style.cursor = 'pointer';
    filterDropdown.addEventListener('click', () => {
      openModal({
        title: 'Filter Invoices',
        content: `
          <div>
            <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 6px;">Status</label>
            <select id="modal-invoice-status" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
              <option value="all">All Statuses</option>
              <option value="paid">Paid Only</option>
              <option value="pending">Pending</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>
        `,
        confirmText: 'Apply Filter',
        onConfirm: () => {
          const val = document.getElementById('modal-invoice-status').value;
          invoiceFilter = val;
          const label = filterDropdown.querySelector('span');
          if (label) label.innerText = val === 'all' ? 'All Statuses' : val.toUpperCase();
          renderInvoicesTable();
          showToast(`Invoices filtered: ${val}`, 'info');
        }
      });
    });
  }

  // New Invoice
  if (newInvBtn) {
    newInvBtn.addEventListener('click', () => {
      const jobCards = AutoCareStore.getJobCards();
      const jcOptions = jobCards.map(c => `
        <option value="${c.id}">${c.code} - ${c.customer_name} (${c.vehicle_title || c.vehicle_details})</option>
      `).join('');

      openModal({
        title: 'Generate New Invoice',
        content: `
          <div style="display: flex; flex-direction: column; gap: 12px;">
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Select Completed Job Card</label>
              <select id="inv-modal-jc" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
                ${jcOptions}
              </select>
            </div>
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Customer Email</label>
              <input type="email" id="inv-modal-email" value="billing@customer.com" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            </div>
            <div>
              <label style="display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px;">Invoice Amount (৳)</label>
              <input type="number" id="inv-modal-amount" value="7500" style="width: 100%; padding: 9px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 13px;">
            </div>
          </div>
        `,
        confirmText: 'Generate Invoice',
        onConfirm: () => {
          const jcId = document.getElementById('inv-modal-jc').value;
          const email = document.getElementById('inv-modal-email').value;
          const amount = parseFloat(document.getElementById('inv-modal-amount').value) || 0;

          const jc = AutoCareStore.getJobCardById(jcId);
          if (jc) {
            const newInv = AutoCareStore.createInvoice({
              job_card_id: jc.id,
              customer_name: jc.customer_name,
              customer_email: email,
              vehicle_name: jc.vehicle_title || jc.vehicle_details,
              vin: jc.vin,
              total_amount: amount,
              status: 'Pending'
            });

            showToast(`Invoice #${newInv.invoice_number} created successfully!`, 'success');
            renderInvoiceStats();
            renderInvoicesTable();
          }
        }
      });
    });
  }

  // Export List (CSV)
  if (exportBtn) {
    exportBtn.addEventListener('click', () => {
      const invoices = AutoCareStore.getInvoices();
      let csv = 'Invoice Number,Customer,Vehicle,Date,Total Amount,Status\n';
      invoices.forEach(i => {
        csv += `"${i.invoice_number}","${i.customer_name}","${i.vehicle_name}","${i.date}","${i.total_amount}","${i.status}"\n`;
      });

      const blob = new Blob([csv], { type: 'text/csv' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `invoices_${new Date().toISOString().split('T')[0]}.csv`;
      a.click();
      showToast('Invoices exported to CSV!', 'success');
    });
  }
}
