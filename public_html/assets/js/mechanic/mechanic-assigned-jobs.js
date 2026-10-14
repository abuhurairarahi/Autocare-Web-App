/**
 * mechanic-assigned-jobs.js
 * Job "Details" dialog and auto-applying filters. The list and pagination are server-rendered.
 */
(function () {
  'use strict';
  const { el, openModal, showToast, formatMoney, formatDate } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    // Filters apply as soon as a dropdown changes
    const filters = document.querySelector('form.filters');
    filters?.addEventListener('change', () => filters.submit());

    document.querySelector('table.jobs')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action="details"]');
      const row = e.target.closest('tr[data-job-id]');
      if (btn && row) showDetails(row.dataset.jobId, btn);
    });
  });

  async function showDetails(jobId, btn) {
    let job;
    btn.disabled = true;
    try {
      job = await mechanicApi.getJob(jobId);
    } catch (err) {
      showToast(err.message, 'error');
      return;
    } finally {
      btn.disabled = false;
    }

    const pairs = [
      ['Status', job.status + ' (' + job.progress_percentage + '%)'],
      ['Priority', job.priority],
      ['Vehicle', job.vehicle ? job.vehicle + ' · ' + job.license_plate : 'No booking linked'],
      ['Customer', job.owner_name || '—'],
      ['Service', job.service_text],
      ['Customer report', job.issue_description || '—'],
      ['Fault report', job.fault_report || '—'],
      ['Started', formatDate(job.start_date, true)],
      ['Due', job.delivery_date || '—'],
      ['Estimated cost', formatMoney(job.estimated_cost)],
      ['Parts / labor', job.parts.length + ' part line(s), ' + job.labor.reduce((h, l) => h + l.hours, 0) + ' h labor'],
      ['Photos', String(job.photo_count)],
    ];

    openModal({
      title: 'Job ' + job.code,
      content: el('dl', { style: 'display:grid;grid-template-columns:auto 1fr;gap:6px 16px;margin:0;' },
        pairs.flatMap(([k, v]) => [
          el('dt', { style: 'color:#64748b;', text: k }),
          el('dd', { style: 'margin:0;font-weight:600;white-space:pre-wrap;', text: v }),
        ])),
      confirmText: job.is_open ? 'Open Workflow' : 'View Workflow',
      cancelText: 'Close',
      onConfirm: () => { window.location.href = 'mechanic-workflow.php?job_id=' + encodeURIComponent(job.id); },
    });
  }
})();
