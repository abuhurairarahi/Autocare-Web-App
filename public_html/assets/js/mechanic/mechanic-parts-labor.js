/**
 * mechanic-parts-labor.js
 * Add/remove part requests and labor entries for the job on screen, and notify the manager.
 * Tables and totals are server-rendered; every change reloads the page.
 */
(function () {
  'use strict';
  const { el, field, inputStyle, openModal, showToast, reloadWithToast, withBusy, formatMoney } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.querySelector('.pl-wrap');
    const jobId = Number(wrap?.dataset.jobId) || null;

    document.getElementById('pl-job-switch')?.addEventListener('change', (e) => {
      window.location.href = 'mechanic-parts-labor.php?job_id=' + encodeURIComponent(e.target.value);
    });
    if (!jobId) return;

    document.getElementById('pl-add-part')?.addEventListener('click', () => openAddPart(jobId));
    document.getElementById('pl-labor-form')?.addEventListener('submit', (e) => {
      e.preventDefault();
      recordLabor(jobId, e.target);
    });

    wrap.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn) return;
      if (btn.dataset.action === 'remove-part') {
        confirmRemove('Remove this part request?', () => mechanicApi.removePart(Number(btn.closest('tr').dataset.jobPartId)), 'Part request removed.');
      } else if (btn.dataset.action === 'remove-labor') {
        confirmRemove('Remove this labor entry?', () => mechanicApi.removeLabor(Number(btn.closest('tr').dataset.laborId)), 'Labor entry removed.');
      }
    });

    const notifyBtn = document.getElementById('pl-notify');
    notifyBtn?.addEventListener('click', () => withBusy(notifyBtn, async () => {
      try {
        const res = await mechanicApi.notifyManager(jobId, 'parts_labor');
        showToast('Summary sent to ' + (res.sent_to || 'the manager') + '.');
      } catch (err) {
        showToast(err.message, 'error');
      }
    }));
  });

  async function openAddPart(jobId) {
    let catalog;
    try {
      catalog = (await mechanicApi.getPartsLabor(jobId)).catalog;
    } catch (err) {
      showToast(err.message, 'error');
      return;
    }

    const select = el('select', { style: inputStyle },
      catalog.map((p) => el('option', {
        value: p.id,
        disabled: Number(p.stock_quantity) < 1,
        text: p.name + ' · ' + p.sku + ' · ' + formatMoney(p.price) + ' (' + p.stock_quantity + ' ' + (p.unit || '') + ' in stock)',
      })));
    const qty = el('input', { type: 'number', min: '1', max: '100', value: '1', style: inputStyle });
    const error = el('p', { role: 'alert', style: 'margin:0;color:#b91c1c;font-size:13px;' });

    openModal({
      title: 'Request a spare part',
      content: el('div', null,
        field('Part', select),
        field('Quantity', qty),
        el('p', { style: 'margin:0 0 8px;font-size:12px;color:#64748b;', text: 'The request goes to the workshop manager for approval.' }),
        error),
      confirmText: 'Add Part',
      onConfirm: async () => {
        const quantity = Number(qty.value);
        if (!Number.isInteger(quantity) || quantity < 1 || quantity > 100) {
          error.textContent = 'Quantity must be a whole number from 1 to 100.';
          return false;
        }
        try {
          await mechanicApi.addPart(jobId, Number(select.value), quantity);
          reloadWithToast('Part requested. Waiting for manager approval.');
        } catch (err) {
          error.textContent = err.message;
          return false;
        }
      },
    });
  }

  async function recordLabor(jobId, form) {
    const data = new FormData(form);
    const labor = {
      job_id: jobId,
      start_time: data.get('start_time'),
      end_time: data.get('end_time'),
      description: String(data.get('description') || '').trim(),
    };
    if (!labor.start_time || !labor.end_time) return showToast('Enter a start and end time.', 'error');
    if (labor.end_time <= labor.start_time) return showToast('End time must be after start time.', 'error');
    if (labor.description.length < 3) return showToast('Describe the task (at least 3 characters).', 'error');

    const btn = document.querySelector('button[form="pl-labor-form"]');
    await withBusy(btn, async () => {
      try {
        const res = await mechanicApi.addLabor(labor);
        reloadWithToast('Recorded ' + res.hours + ' labor hour(s).');
      } catch (err) {
        showToast(err.message, 'error');
      }
    });
  }

  function confirmRemove(question, action, done) {
    openModal({
      title: question,
      content: el('p', { style: 'margin:0;', text: 'This cannot be undone.' }),
      confirmText: 'Remove',
      onConfirm: async () => {
        try {
          await action();
          reloadWithToast(done);
        } catch (err) {
          showToast(err.message, 'error');
          return false;
        }
      },
    });
  }
})();
