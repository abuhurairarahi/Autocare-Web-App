/**
 * mechanic-repair-photos.js
 * Upload, view and delete repair photos for the job on screen.
 */
(function () {
  'use strict';
  const { el, field, inputStyle, openModal, showToast, reloadWithToast } = AutoCare;
  const MAX_BYTES = 5 * 1024 * 1024;
  const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

  document.addEventListener('DOMContentLoaded', () => {
    const wrap = document.querySelector('.rp-wrap');
    const jobId = Number(wrap?.dataset.jobId) || null;

    document.getElementById('rp-job-switch')?.addEventListener('change', (e) => {
      window.location.href = 'mechanic-repair-photos.php?job_id=' + encodeURIComponent(e.target.value);
    });

    wrap?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      if (!btn || !jobId) return;
      const card = btn.closest('.rp-card');
      if (btn.dataset.action === 'upload') openUpload(jobId);
      if (btn.dataset.action === 'view' && card) viewPhoto(card.dataset.photoSrc, card.dataset.photoCaption);
      if (btn.dataset.action === 'delete' && card) confirmDelete(Number(card.dataset.photoId));
    });
  });

  function openUpload(jobId) {
    const file = el('input', { type: 'file', accept: TYPES.join(','), style: inputStyle });
    const caption = el('input', { type: 'text', maxlength: '255', placeholder: 'e.g. Front bumper damage', style: inputStyle });
    const preview = el('img', { alt: '', style: 'display:none;max-width:100%;max-height:220px;border-radius:8px;margin-bottom:12px;' });
    const error = el('p', { role: 'alert', style: 'margin:0;color:#b91c1c;font-size:13px;' });

    file.addEventListener('change', () => {
      const f = file.files[0];
      error.textContent = '';
      if (!f) { preview.style.display = 'none'; return; }
      preview.src = URL.createObjectURL(f);
      preview.style.display = 'block';
    });

    openModal({
      title: 'Upload repair photo',
      content: el('div', null, field('Photo *', file), preview, field('Caption', caption), error),
      confirmText: 'Upload',
      onConfirm: async () => {
        const f = file.files[0];
        if (!f) { error.textContent = 'Choose a photo first.'; return false; }
        if (!TYPES.includes(f.type)) { error.textContent = 'Only JPEG, PNG or WebP images are allowed.'; return false; }
        if (f.size > MAX_BYTES) { error.textContent = 'Photo must be 5 MB or smaller.'; return false; }

        const form = new FormData();
        form.append('job_id', String(jobId));
        form.append('description', caption.value.trim());
        form.append('photo', f);
        try {
          await mechanicApi.uploadPhoto(form);
          reloadWithToast('Photo uploaded. The customer can see it in Live Repair Tracking.');
        } catch (err) {
          error.textContent = err.message;
          return false;
        }
      },
    });
  }

  function viewPhoto(src, caption) {
    openModal({
      title: caption || 'Repair photo',
      content: el('img', { src: src, alt: caption || 'Repair photo', style: 'width:100%;border-radius:8px;' }),
      cancelText: 'Close',
      hideConfirm: true,
    });
  }

  function confirmDelete(photoId) {
    openModal({
      title: 'Delete this photo?',
      content: el('p', { style: 'margin:0;', text: 'The customer will no longer see it. This cannot be undone.' }),
      confirmText: 'Delete',
      onConfirm: async () => {
        try {
          await mechanicApi.deletePhoto(photoId);
          reloadWithToast('Photo deleted.');
        } catch (err) {
          showToast(err.message, 'error');
          return false;
        }
      },
    });
  }
})();
