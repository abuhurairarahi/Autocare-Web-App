/**
 * vehicleowner-notifications.js
 * Category chips, unread-only toggle, "Load More" paging and "Mark all as read".
 * The feed itself is server-rendered.
 */
(function () {
  'use strict';
  const { showToast, reloadWithToast, withBusy } = AutoCare;
  const PAGE_SIZE = 15;

  document.addEventListener('DOMContentLoaded', () => {
    const items = Array.from(document.querySelectorAll('.vo-notification[data-category]'));
    const chips = document.querySelectorAll('.vo-chip[data-filter]');
    const unreadBtn = document.getElementById('vo-unread-only');
    const loadMore = document.getElementById('vo-load-more');
    const empty = document.getElementById('vo-notif-empty');
    let category = 'all';
    let unreadOnly = false;
    let shown = PAGE_SIZE;

    function apply() {
      const matching = items.filter((n) => (category === 'all' || n.dataset.category === category) && (!unreadOnly || n.dataset.unread === '1'));
      items.forEach((n) => { n.hidden = true; });
      matching.slice(0, shown).forEach((n) => { n.hidden = false; });
      document.querySelectorAll('.vo-notif-group').forEach((g) => {
        g.hidden = !g.querySelector('.vo-notification:not([hidden])');
      });
      if (loadMore) loadMore.hidden = matching.length <= shown;
      if (empty) empty.hidden = matching.length > 0 || items.length === 0;
    }

    chips.forEach((chip) => chip.addEventListener('click', () => {
      chips.forEach((c) => c.classList.toggle('vo-chip--active', c === chip));
      category = chip.dataset.filter;
      shown = PAGE_SIZE;
      apply();
    }));

    unreadBtn?.addEventListener('click', () => {
      unreadOnly = !unreadOnly;
      unreadBtn.setAttribute('aria-pressed', String(unreadOnly));
      unreadBtn.style.fontWeight = unreadOnly ? '700' : '';
      shown = PAGE_SIZE;
      apply();
    });

    loadMore?.addEventListener('click', () => { shown += PAGE_SIZE; apply(); });

    const markAll = document.getElementById('vo-mark-all');
    markAll?.addEventListener('click', () => withBusy(markAll, async () => {
      try {
        const res = await ownerApi.markAllRead();
        reloadWithToast(res.marked ? 'Marked ' + res.marked + ' message(s) as read.' : 'Everything is already read.');
      } catch (err) {
        showToast(err.message, 'error');
      }
    }));

    apply();
  });
})();
