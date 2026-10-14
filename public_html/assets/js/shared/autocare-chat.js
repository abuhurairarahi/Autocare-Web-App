/**
 * autocare-chat.js
 * Chat thread behaviour shared by the Mechanic and Vehicle Owner chat pages:
 * load the thread, group by day, poll for new messages, send, filter contacts.
 * Each page supplies its own markup through renderMessage / renderDivider.
 * Requires autocare-core.js.
 */
(function () {
  'use strict';
  const { showToast } = AutoCare;
  const POLL_MS = 8000;

  /**
   * options: {
   *   getChat(contactId, afterId) -> Promise<{messages, contact}>,
   *   sendChat(contactId, text)   -> Promise<message>,
   *   contactId, feed, form, input, sendButton?,
   *   renderMessage(message, contact) -> Node,
   *   renderDivider(label) -> Node,
   *   searchInput?, contactItems? (NodeList with data-contact-name / data-contact-id)
   * }
   */
  function initChat(o) {
    let lastId = 0;
    let lastDay = null;
    let contact = null;
    let sending = false;

    // Contact list: click to open, search to filter
    (o.contactItems || []).forEach((item) => {
      item.addEventListener('click', (e) => {
        e.preventDefault();
        window.location.href = '?contact_id=' + encodeURIComponent(item.dataset.contactId);
      });
    });
    o.searchInput?.addEventListener('input', () => {
      const q = o.searchInput.value.trim().toLowerCase();
      (o.contactItems || []).forEach((item) => {
        const row = item.closest('li') || item;
        row.hidden = q !== '' && !item.dataset.contactName.toLowerCase().includes(q);
      });
    });

    if (!o.contactId || !o.feed) return;

    function dayLabel(created) {
      const d = new Date(String(created).replace(' ', 'T'));
      const today = new Date();
      const yesterday = new Date(Date.now() - 86400000);
      const same = (a, b) => a.toDateString() === b.toDateString();
      const date = d.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
      if (same(d, today)) return 'Today, ' + date;
      if (same(d, yesterday)) return 'Yesterday, ' + date;
      return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
    }

    function append(messages) {
      if (lastId === 0 && messages.length) o.feed.replaceChildren(); // drop the empty-thread placeholder
      const nearBottom = o.feed.scrollHeight - o.feed.scrollTop - o.feed.clientHeight < 120;
      messages.forEach((m) => {
        if (m.id <= lastId) return;
        const day = String(m.created_at).slice(0, 10);
        if (day !== lastDay) {
          o.feed.appendChild(o.renderDivider(dayLabel(m.created_at)));
          lastDay = day;
        }
        o.feed.appendChild(o.renderMessage(m, contact));
        lastId = m.id;
      });
      if (nearBottom || messages.some((m) => m.is_mine)) o.feed.scrollTop = o.feed.scrollHeight;
    }

    async function load(initial) {
      try {
        const data = await o.getChat(o.contactId, initial ? 0 : lastId);
        contact = data.contact;
        if (initial) {
          o.feed.replaceChildren();
          if (!data.messages.length) {
            o.feed.appendChild(o.renderDivider('No messages yet. Say hello!'));
          }
        }
        append(data.messages);
        if (initial) o.feed.scrollTop = o.feed.scrollHeight;
      } catch (err) {
        if (initial) showToast(err.message, 'error');
      }
    }

    async function send() {
      const text = o.input.value.trim();
      if (!text || sending) return;
      if (text.length > 2000) {
        showToast('Messages can be at most 2000 characters.', 'error');
        return;
      }
      sending = true;
      if (o.sendButton) o.sendButton.disabled = true;
      try {
        const message = await o.sendChat(o.contactId, text);
        o.input.value = '';
        append([message]);
      } catch (err) {
        showToast(err.message, 'error');
      } finally {
        sending = false;
        if (o.sendButton) o.sendButton.disabled = false;
        o.input.focus();
      }
    }

    o.form?.addEventListener('submit', (e) => { e.preventDefault(); send(); });
    o.sendButton?.addEventListener('click', (e) => { e.preventDefault(); send(); });
    o.input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    load(true);
    setInterval(() => { if (!document.hidden) load(false); }, POLL_MS);
  }

  AutoCare.initChat = initChat;
})();
