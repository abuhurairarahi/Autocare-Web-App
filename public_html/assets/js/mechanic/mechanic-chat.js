/**
 * mechanic-chat.js
 * Mechanic chat: message bubbles for this page's markup; behaviour comes from autocare-chat.js.
 */
(function () {
  'use strict';
  const { el, formatTime, showToast } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('.chat-layout');
    if (!root) return;
    const { contactName, contactInitials, contactColor } = root.dataset;

    const bubbleBody = (m) => [
      el('span', { style: 'white-space:pre-wrap;', text: m.message }),
      m.attachments.map((src) => el('img', { src: src, alt: 'Attachment', style: 'display:block;max-width:220px;border-radius:6px;margin-top:6px;' })),
    ];

    AutoCare.initChat({
      getChat: (id, afterId) => mechanicApi.request('chat.php?contact_id=' + encodeURIComponent(id) + (afterId ? '&after_id=' + afterId : '')),
      sendChat: (id, text) => mechanicApi.sendChat(id, text),
      contactId: Number(root.dataset.contactId) || null,
      feed: document.getElementById('chat-feed'),
      input: document.getElementById('chat-input'),
      sendButton: document.getElementById('chat-send'),
      searchInput: document.getElementById('chat-search'),
      contactItems: document.querySelectorAll('.chat-item[data-contact-id]'),
      renderDivider: (label) => el('div', { class: 'chat-date-divider' }, el('span', { text: label })),
      renderMessage: (m) => m.is_mine
        ? el('div', { class: 'msg msg--sent' },
            el('div', { class: 'msg__body' },
              el('div', { class: 'msg__meta' },
                el('span', { class: 'msg__time', text: formatTime(m.created_at) }),
                el('span', { class: 'msg__name', text: 'You' })),
              el('div', { class: 'msg__bubble msg__bubble--sent' }, bubbleBody(m))),
            el('span', { class: 'chat-avatar chat-avatar--blue chat-avatar--sm', text: 'Me' }))
        : el('div', { class: 'msg msg--received' },
            el('span', { class: 'chat-avatar ' + contactColor + ' chat-avatar--sm', text: contactInitials }),
            el('div', { class: 'msg__body' },
              el('div', { class: 'msg__meta' },
                el('span', { class: 'msg__name', text: contactName }),
                el('span', { class: 'msg__time', text: formatTime(m.created_at) })),
              el('div', { class: 'msg__bubble' }, bubbleBody(m)))),
    });

    document.querySelectorAll('[data-unsupported]').forEach((btn) => {
      btn.addEventListener('click', () => showToast('Attachments and emoji are not supported in chat yet.', 'info'));
    });
  });
})();
