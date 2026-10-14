/**
 * vehicleowner-service-history.js
 * Vehicle filter auto-apply and CSV export of all service records.
 */
(function () {
  'use strict';
  const { showToast, withBusy } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    const filter = document.getElementById('vo-history-filter');
    filter?.addEventListener('change', () => filter.submit());

    const exportBtn = document.getElementById('vo-history-export');
    exportBtn?.addEventListener('click', () => withBusy(exportBtn, exportCsv));
  });

  async function exportCsv() {
    const vehicleId = new URLSearchParams(window.location.search).get('vehicle_id');
    let history;
    try {
      history = await ownerApi.request('service-history.php' + (vehicleId ? '?vehicle_id=' + encodeURIComponent(vehicleId) : ''));
    } catch (err) {
      showToast(err.message, 'error');
      return;
    }

    const header = ['Service Date', 'Vehicle', 'Plate', 'Workshop', 'Category', 'Mechanic', 'Cost', 'Status', 'Invoice'];
    const rows = history.records.map((r) => [
      r.service_date, r.year + ' ' + r.make + ' ' + r.model, r.license_plate, r.workshop_name || '',
      r.category_name || r.service_text, r.mechanic_name || '', Number(r.cost).toFixed(2), r.status, r.invoice_number || '',
    ]);
    const csv = [header, ...rows].map((row) => row.map(csvCell).join(',')).join('\r\n');

    const url = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = 'service-history.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  /** Quote a CSV cell and neutralise spreadsheet formula injection. */
  function csvCell(value) {
    let s = String(value == null ? '' : value);
    if (/^[=+\-@\t\r]/.test(s)) s = "'" + s;
    return '"' + s.replace(/"/g, '""') + '"';
  }
})();
