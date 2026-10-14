/**
 * vehicleowner-invoices.js
 * Invoice detail dialog (with parts/labor line items) and print.
 * The list, filters and pagination are server-rendered.
 */
(function () {
  'use strict';
  const { el, openModal, showToast, formatMoney, formatDate } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.vo-invoices-table')?.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-action]');
      const row = e.target.closest('tr[data-invoice-id]');
      if (!trigger || !row) return;
      e.preventDefault();
      openInvoice(row.dataset.invoiceId, trigger.dataset.action === 'print');
    });
  });

  async function openInvoice(id, print) {
    let inv;
    try {
      inv = await ownerApi.getInvoice(id);
    } catch (err) {
      showToast(err.message, 'error');
      return;
    }

    const cell = 'padding:6px 8px;border-bottom:1px solid #e2e8f0;';
    const num = cell + 'text-align:right;';
    const rows = [
      ...inv.parts.map((p) => [p.description, 'Part', p.quantity, p.unit_price, p.total]),
      ...inv.labor.map((l) => [l.description, 'Labor', Number(l.hours) + ' h', l.hourly_rate, l.total]),
    ];

    const table = rows.length
      ? el('table', { style: 'width:100%;border-collapse:collapse;font-size:13px;margin:12px 0;' },
          el('thead', null, el('tr', null,
            ['Item', 'Type', 'Qty', 'Unit', 'Total'].map((h, i) => el('th', { style: (i > 1 ? num : cell) + 'color:#64748b;font-weight:600;', text: h })))),
          el('tbody', null, rows.map(([desc, type, qty, unit, total]) => el('tr', null,
            el('td', { style: cell, text: desc }),
            el('td', { style: cell, text: type }),
            el('td', { style: num, text: String(qty) }),
            el('td', { style: num, text: formatMoney(unit) }),
            el('td', { style: num, text: formatMoney(total) })))))
      : el('p', { style: 'color:#64748b;', text: 'No itemised parts or labor recorded for this job.' });

    const vehicle = inv.make ? inv.make + ' ' + inv.model + ' (' + inv.license_plate + ')' : '—';
    const content = el('div', null,
      definitionList([
        ['Status', inv.status],
        ['Issued', formatDate(inv.issued_date)],
        ['Paid', inv.paid_date ? formatDate(inv.paid_date) : '—'],
        ['Job card', inv.job_code + (inv.service_text ? ' · ' + inv.service_text : '')],
        ['Vehicle', vehicle],
      ]),
      table,
      el('p', { style: 'text-align:right;font-size:16px;font-weight:700;margin:0;', text: 'Invoice total: ' + formatMoney(inv.total_amount) })
    );

    openModal({
      title: 'Invoice ' + inv.invoice_number,
      content: content,
      confirmText: 'Print',
      cancelText: 'Close',
      onConfirm: () => { window.print(); return false; },
    });
    if (print) setTimeout(() => window.print(), 50);
  }

  function definitionList(pairs) {
    return el('dl', { style: 'display:grid;grid-template-columns:auto 1fr;gap:4px 16px;margin:0;' },
      pairs.flatMap(([term, value]) => [
        el('dt', { style: 'color:#64748b;', text: term }),
        el('dd', { style: 'margin:0;font-weight:600;', text: value }),
      ]));
  }
})();
