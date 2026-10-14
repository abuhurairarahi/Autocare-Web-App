/**
 * vehicleowner-repair-estimates.js
 * Expand/collapse finished estimates, approve, and ask the manager for clarification.
 */
(function () {
  'use strict';
  const { el, field, inputStyle, openModal, showToast, reloadWithToast } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.vo-collapsed-estimate[data-toggle]').forEach((header) => {
      const toggle = () => {
        const detail = document.getElementById(header.dataset.toggle);
        detail.hidden = !detail.hidden;
        header.setAttribute('aria-expanded', String(!detail.hidden));
      };
      header.addEventListener('click', toggle);
      header.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });

    document.querySelector('.vo-estimates')?.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-action]');
      const card = e.target.closest('[data-estimate-id]');
      if (!btn || !card) return;
      const id = Number(card.dataset.estimateId);
      const code = card.querySelector('h2')?.textContent || 'this estimate';
      if (btn.dataset.action === 'approve') confirmApprove(id, code);
      if (btn.dataset.action === 'clarify') askQuestion(id, code);
    });
  });

  function confirmApprove(id, code) {
    openModal({
      title: 'Approve ' + code + '?',
      content: el('p', { style: 'margin:0;', text: 'The workshop will go ahead with the work and parts listed at the quoted total.' }),
      confirmText: 'Approve',
      onConfirm: async () => {
        try {
          await ownerApi.approveEstimate(id);
          reloadWithToast(code + ' approved. The workshop has been notified.');
        } catch (err) {
          showToast(err.message, 'error');
          return false;
        }
      },
    });
  }

  function askQuestion(id, code) {
    const text = el('textarea', { rows: '4', maxlength: '1000', style: inputStyle, placeholder: 'e.g. Can the rotors wait until the next service?' });
    const error = el('p', { role: 'alert', style: 'margin:0;color:#b91c1c;font-size:13px;' });
    openModal({
      title: 'Question about ' + code,
      content: el('div', null, field('Your message to the workshop manager', text), error),
      confirmText: 'Send',
      onConfirm: async () => {
        const message = text.value.trim();
        if (message.length < 3) {
          error.textContent = 'Please write at least 3 characters.';
          return false;
        }
        try {
          await ownerApi.requestClarification(id, message);
          showToast('Question sent. You can follow up in Chat.');
        } catch (err) {
          error.textContent = err.message;
          return false;
        }
      },
    });
  }
})();
