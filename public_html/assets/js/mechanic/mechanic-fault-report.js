/**
 * mechanic-fault-report.js
 * Submit a fault report (and optional photo) for the job on screen.
 */
(function () {
  'use strict';
  const { showToast, reloadWithToast, withBusy } = AutoCare;
  const PHOTO_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('fr-job-switch')?.addEventListener('change', (e) => {
      window.location.href = 'mechanic-fault-report.php?job_id=' + encodeURIComponent(e.target.value);
    });

    document.getElementById('fr-view-all')?.addEventListener('click', (e) => {
      document.querySelectorAll('.fr-report-card[data-extra]').forEach((card) => { card.hidden = false; });
      e.currentTarget.remove();
    });

    const form = document.getElementById('fr-form');
    if (!form) return;
    const photo = document.getElementById('fault-photos');
    const photoName = document.getElementById('fr-photo-name');

    photo?.addEventListener('change', () => {
      const f = photo.files[0];
      if (f && (!PHOTO_TYPES.includes(f.type) || f.size > 5 * 1024 * 1024)) {
        showToast('Photo must be a JPEG, PNG or WebP image up to 5 MB.', 'error');
        photo.value = '';
      }
      if (photoName) photoName.textContent = photo.files[0] ? 'Attached: ' + photo.files[0].name : 'JPEG, PNG or WebP up to 5MB';
    });

    form.querySelector('.fr-btn--outline')?.addEventListener('click', () => {
      form.reset();
      if (photoName) photoName.textContent = 'JPEG, PNG or WebP up to 5MB';
    });

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      submit(form, Number(form.dataset.jobId), photo?.files[0] || null);
    });
  });

  async function submit(form, jobId, photoFile) {
    const data = new FormData(form);
    const report = {
      job_id: jobId,
      title: String(data.get('title') || '').trim(),
      category: data.get('category') || '',
      severity: data.get('severity') || '',
      description: String(data.get('description') || '').trim(),
      estimated_cost: String(data.get('estimated_cost') || '').trim(),
      recommendation: String(data.get('recommendation') || '').trim(),
    };
    if (report.title.length < 3) return showToast('Fault title must be at least 3 characters.', 'error');
    if (!report.category) return showToast('Choose a category.', 'error');
    if (!report.severity) return showToast('Choose a severity.', 'error');
    if (report.estimated_cost !== '' && !(Number(report.estimated_cost) >= 0)) return showToast('Estimated cost must be a positive number.', 'error');

    await withBusy(form.querySelector('button[type="submit"]'), async () => {
      try {
        await mechanicApi.submitFaultReport(report);
      } catch (err) {
        showToast(err.message, 'error');
        return;
      }

      if (photoFile) {
        const upload = new FormData();
        upload.append('job_id', String(jobId));
        upload.append('description', ('Fault: ' + report.title).slice(0, 255));
        upload.append('photo', photoFile);
        try {
          await mechanicApi.uploadPhoto(upload);
        } catch (err) {
          reloadWithToast('Report saved, but the photo failed to upload: ' + err.message, 'warning');
          return;
        }
      }
      reloadWithToast('Fault report submitted.');
    });
  }
})();
