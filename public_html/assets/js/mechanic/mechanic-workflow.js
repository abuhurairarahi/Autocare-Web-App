/**
 * mechanic-workflow.js
 * Status changes (JobCards.status) and "Send Estimation" for the job on screen.
 */
(function () {
  'use strict';
  const { el, field, inputStyle, openModal, showToast, reloadWithToast, withBusy } = AutoCare;

  // Must match MECHANIC_STATUS_FLOW in api/repair-stages.php
  const STATUSES = ['Diagnosis', 'Awaiting Parts', 'Repairing', 'Testing', 'Completed'];

  document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('.wf-header[data-job-id]');
    if (!header) return;
    const jobId = Number(header.dataset.jobId);
    const current = header.dataset.jobStatus;

    document.getElementById('wf-change-status')?.addEventListener('click', () => openStatusModal(jobId, current));

    document.getElementById('wf-complete')?.addEventListener('click', () => {
      openModal({
        title: 'Complete this job?',
        content: el('p', { style: 'margin:0;', text: 'The job will be marked Completed and locked. The manager and customer will see it as finished.' }),
        confirmText: 'Mark Completed',
        onConfirm: () => setStatus(jobId, 'Completed'),
      });
    });

    const estimateBtn = document.getElementById('wf-send-estimate');
    estimateBtn?.addEventListener('click', () => withBusy(estimateBtn, async () => {
      try {
        const res = await mechanicApi.notifyManager(jobId, 'estimate');
        showToast('Sent to ' + (res.sent_to || 'the manager') + ' for estimation.');
      } catch (err) {
        showToast(err.message, 'error');
      }
    }));
  });

  function openStatusModal(jobId, current) {
    const select = el('select', { style: inputStyle },
      STATUSES.map((s) => el('option', { value: s, selected: s === current, text: s })));
    openModal({
      title: 'Change job status',
      content: field('New status', select),
      confirmText: 'Update Status',
      onConfirm: () => (select.value === current ? true : setStatus(jobId, select.value)),
    });
  }

  async function setStatus(jobId, status) {
    try {
      await mechanicApi.updateJobStatus(jobId, status);
      reloadWithToast('Job moved to ' + status + '.');
    } catch (err) {
      showToast(err.message, 'error');
      return false;
    }
  }
})();
