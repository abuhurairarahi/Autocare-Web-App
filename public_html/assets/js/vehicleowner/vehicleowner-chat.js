/**
 * vehicleowner-chat.js
 * Owner chat: message bubbles for this page's markup; behaviour comes from autocare-chat.js.
 */
(function () {
  'use strict';
  const { el, formatTime, initials, showToast } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.vo-chat');
    if (!root) return;
    const contactName = root.dataset.contactName;
    const contactAvatar = root.dataset.contactAvatar;

    const avatar = () => el('span', { class: 'vo-avatar vo-avatar--sm' },
      contactAvatar
        ? el('img', { src: contactAvatar, alt: contactName })
        : el('span', { class: 'vo-avatar__initials', text: initials(contactName) }));

    const bubble = (m, mine) => el('div', { class: 'vo-bubble ' + (mine ? 'vo-bubble--self' : 'vo-bubble--other') },
      el('p', { style: 'white-space:pre-wrap;', text: m.message }),
      m.attachments.map((src) => el('figure', { class: 'vo-attachment' }, el('img', { src: src, alt: 'Attachment' }))));

    const form = document.getElementById('vo-chat-form');
    AutoCare.initChat({
      getChat: (id, afterId) => ownerApi.request('chat.php?contact_id=' + encodeURIComponent(id) + (afterId ? '&after_id=' + afterId : '')),
      sendChat: (id, text) => ownerApi.sendChat(id, text),
      contactId: Number(root.dataset.contactId) || null,
      feed: document.getElementById('vo-chat-feed'),
      form: form,
      input: form?.querySelector('textarea'),
      searchInput: document.getElementById('vo-chat-search'),
      contactItems: document.querySelectorAll('.vo-conversation[data-contact-id]'),
      renderDivider: (label) => el('div', { class: 'vo-date-divider' }, el('span', { text: label })),
      renderMessage: (m) => m.is_mine
        ? el('div', { class: 'vo-message vo-message--self' },
            el('div', { class: 'vo-message__body' },
              el('p', { class: 'vo-message__meta' },
                el('span', { class: 'vo-message__time', text: formatTime(m.created_at) }), ' ',
                el('span', { class: 'vo-message__sender', text: 'You' })),
              bubble(m, true)))
        : el('div', { class: 'vo-message vo-message--other' },
            avatar(),
            el('div', { class: 'vo-message__body' },
              el('p', { class: 'vo-message__meta' },
                el('span', { class: 'vo-message__sender', text: contactName }), ' ',
                el('span', { class: 'vo-message__time', text: formatTime(m.created_at) })),
              bubble(m, false))),
    });

    document.querySelectorAll('[data-unsupported]').forEach((btn) => {
      btn.addEventListener('click', () => showToast('Attachments are not supported in chat yet.', 'info'));
    });
  });
})();
