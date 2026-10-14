/**
 * vehicleowner-repair-tracking.js
 * Switch between active repairs and view repair photos full size.
 * The tracking data itself is rendered server-side.
 */
(function () {
  'use strict';
  const { el, openModal, showToast } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('vo-job-switch')?.addEventListener('change', (e) => {
      window.location.href = 'vehicleowner-repair-tracking.php?job_id=' + encodeURIComponent(e.target.value);
    });

    document.querySelectorAll('.vo-gallery__item[data-photo-src]').forEach((figure) => {
      figure.addEventListener('click', () => showPhoto(figure.dataset.photoSrc, figure.dataset.photoCaption));
    });

    document.getElementById('vo-gallery-all')?.addEventListener('click', showAllPhotos);
  });

  function showPhoto(src, caption) {
    openModal({
      title: caption || 'Repair photo',
      content: el('img', { src: src, alt: caption || 'Repair photo', style: 'width:100%;border-radius:8px;' }),
      cancelText: 'Close',
      hideConfirm: true,
    });
  }

  async function showAllPhotos() {
    const jobId = document.querySelector('.vo-grid[data-job-id]')?.dataset.jobId;
    if (!jobId) return;
    try {
      const job = await ownerApi.getRepairTracking(jobId);
      const grid = el('div', { style: 'display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;' },
        job.photos.map((p) => el('figure', { style: 'margin:0;' },
          el('img', {
            src: p.photo_url, alt: p.description || 'Repair photo',
            style: 'width:100%;height:110px;object-fit:cover;border-radius:6px;cursor:zoom-in;',
            on: { click: () => showPhoto(p.photo_url, p.description) },
          }),
          el('figcaption', { style: 'font-size:12px;color:#64748b;margin-top:4px;', text: (p.description || '') + ' · ' + AutoCare.formatDate(p.uploaded_at, true) })
        ))
      );
      openModal({ title: 'All photos (' + job.photos.length + ')', content: grid, cancelText: 'Close', hideConfirm: true });
    } catch (err) {
      showToast(err.message, 'error');
    }
  }
})();
