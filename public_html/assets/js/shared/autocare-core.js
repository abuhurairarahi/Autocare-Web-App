/**
 * autocare-core.js
 * Shared helpers for the Mechanic and Vehicle Owner panels:
 * safe DOM building, escaping, toasts, modals, money/date formatting
 * and a JSON API client that sends the session CSRF token.
 */
(function () {
  'use strict';

  /** Escape a value for use inside an HTML string. Prefer el()/textContent where possible. */
  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  /**
   * Build an element. attrs: { class, text, dataset, style, on: {event: fn}, ...attributes }.
   * Children that are strings/numbers become text nodes, never HTML.
   */
  function el(tag, attrs, ...children) {
    const node = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([key, value]) => {
      if (value == null || value === false) return;
      if (key === 'class') node.className = value;
      else if (key === 'text') node.textContent = value;
      else if (key === 'dataset') Object.assign(node.dataset, value);
      else if (key === 'style') node.style.cssText = value;
      else if (key === 'on') Object.entries(value).forEach(([evt, fn]) => node.addEventListener(evt, fn));
      else node.setAttribute(key, value === true ? '' : value);
    });
    children.flat().forEach((child) => {
      if (child == null || child === false) return;
      node.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
    });
    return node;
  }

  /** Font Awesome icon element. */
  function icon(classes) {
    return el('i', { class: classes, 'aria-hidden': 'true' });
  }

  function formatMoney(amount) {
    const n = Number(amount) || 0;
    return '৳' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function formatDate(value, withTime) {
    if (!value) return '-';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d)) return String(value);
    const opts = { month: 'short', day: '2-digit', year: 'numeric' };
    if (withTime) Object.assign(opts, { hour: '2-digit', minute: '2-digit' });
    return d.toLocaleString('en-US', opts);
  }

  function formatTime(value) {
    if (!value) return '';
    const d = new Date(String(value).replace(' ', 'T'));
    return isNaN(d) ? String(value) : d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
  }

  function initials(name) {
    return String(name || '?').trim().split(/\s+/).map((p) => p.charAt(0).toUpperCase()).join('').slice(0, 2) || '?';
  }

  /* ---------- Toasts ---------- */
  function showToast(message, type, duration) {
    const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
    let container = document.getElementById('autocare-toast-container');
    if (!container) {
      container = el('div', {
        id: 'autocare-toast-container',
        style: 'position:fixed;top:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;',
        'aria-live': 'polite',
      });
      document.body.appendChild(container);
    }
    const toast = el('div', {
      role: type === 'error' ? 'alert' : 'status',
      style: 'padding:12px 18px;border-radius:10px;color:#fff;font-size:13.5px;font-weight:600;' +
        'box-shadow:0 10px 25px rgba(0,0,0,.2);pointer-events:auto;max-width:360px;' +
        'background:' + (colors[type] || colors.success) + ';transition:opacity .3s ease;',
      text: message,
    });
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 350);
    }, duration || 3500);
  }

  /* ---------- Modal ----------
   * content: a Node (build it with el()). onConfirm may return false to keep the modal open,
   * or a Promise that resolves to false.
   */
  function openModal(options) {
    const { title = 'Confirm', content = null, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm, hideConfirm = false } = options || {};
    document.getElementById('autocare-modal-overlay')?.remove();

    const confirmBtn = el('button', {
      type: 'button',
      style: 'padding:9px 20px;border-radius:8px;border:none;background:#2563eb;color:#fff;font-weight:600;cursor:pointer;',
      text: confirmText,
    });
    const cancelBtn = el('button', {
      type: 'button',
      style: 'padding:9px 18px;border-radius:8px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-weight:600;cursor:pointer;',
      text: cancelText,
    });
    const closeX = el('button', { type: 'button', 'aria-label': 'Close', style: 'background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;', text: '×' });

    const box = el('div', {
      role: 'dialog', 'aria-modal': 'true', 'aria-label': title,
      style: 'background:#fff;border-radius:14px;width:92%;max-width:540px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 20px 40px rgba(0,0,0,.25);overflow:hidden;',
    },
      el('div', { style: 'padding:16px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;background:#f8fafc;' },
        el('h3', { style: 'margin:0;font-size:17px;font-weight:700;color:#0f172a;', text: title }), closeX),
      el('div', { style: 'padding:22px;font-size:14px;color:#334155;line-height:1.6;overflow-y:auto;' }, content),
      el('div', { style: 'padding:14px 22px;background:#f8fafc;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:12px;' },
        cancelBtn, hideConfirm ? null : confirmBtn)
    );
    const overlay = el('div', {
      id: 'autocare-modal-overlay',
      style: 'position:fixed;inset:0;background:rgba(15,23,42,.6);display:flex;align-items:center;justify-content:center;z-index:99998;',
    }, box);

    const close = () => overlay.remove();
    closeX.addEventListener('click', close);
    cancelBtn.addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    document.addEventListener('keydown', function onKey(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onKey); }
    });
    confirmBtn.addEventListener('click', async () => {
      if (!onConfirm) return close();
      confirmBtn.disabled = true;
      try {
        const keepOpen = (await onConfirm()) === false;
        if (!keepOpen) close();
      } finally {
        confirmBtn.disabled = false;
      }
    });

    document.body.appendChild(overlay);
    (box.querySelector('input, select, textarea') || confirmBtn).focus();
    return { close, box };
  }

  /** Labeled form field for modals. */
  function field(label, control) {
    return el('label', { style: 'display:block;margin-bottom:14px;font-size:13px;font-weight:600;color:#334155;' },
      label, control);
  }

  const inputStyle = 'display:block;width:100%;box-sizing:border-box;margin-top:6px;padding:9px 10px;border-radius:6px;border:1px solid #cbd5e1;font-size:13px;font-weight:400;font-family:inherit;';

  /* ---------- API client ---------- */
  function createApi(basePath) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    /**
     * Returns `data` on success. Throws Error(message) on failure so callers can show it.
     * body: plain object (sent as JSON) or FormData (sent as multipart).
     */
    async function request(endpoint, method, body) {
      const opts = { method: method || 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' };
      if (opts.method !== 'GET') opts.headers['X-CSRF-Token'] = csrf();
      if (body instanceof FormData) opts.body = body;
      else if (body !== undefined) {
        opts.headers['Content-Type'] = 'application/json';
        opts.body = JSON.stringify(body);
      }

      let res;
      try {
        res = await fetch(basePath + endpoint, opts);
      } catch (err) {
        throw new Error('Network error. Check your connection.');
      }
      if (res.status === 401) {
        window.location.href = '../login.html';
        throw new Error('Session expired');
      }
      let json = null;
      try { json = await res.json(); } catch (err) { /* non-JSON error page */ }
      if (!res.ok || !json || json.success !== true) {
        throw new Error((json && json.error) || 'Request failed (' + res.status + ')');
      }
      return json.data;
    }

    return {
      request,
      get: (endpoint) => request(endpoint, 'GET'),
      post: (endpoint, body) => request(endpoint, 'POST', body),
    };
  }

  /** Disable a button while an async action runs. */
  async function withBusy(button, fn) {
    if (button) button.disabled = true;
    try {
      return await fn();
    } finally {
      if (button) button.disabled = false;
    }
  }

  /** Reload the page (server re-renders the data) and show a toast once it loads. */
  function reloadWithToast(message, type) {
    try { sessionStorage.setItem('autocare-flash', JSON.stringify({ message, type: type || 'success' })); } catch (e) { /* storage blocked */ }
    window.location.reload();
  }

  function showFlash() {
    let flash = null;
    try {
      flash = JSON.parse(sessionStorage.getItem('autocare-flash') || 'null');
      sessionStorage.removeItem('autocare-flash');
    } catch (e) { /* storage blocked or bad JSON */ }
    if (flash && flash.message) showToast(flash.message, flash.type);
  }

  /** Make a topbar icon a keyboard-accessible control that runs onActivate. */
  function makeControl(node, role, label, onActivate) {
    node.setAttribute('role', role);
    node.setAttribute('tabindex', '0');
    node.setAttribute('aria-label', label);
    node.style.cursor = 'pointer';
    node.addEventListener('click', onActivate);
    node.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onActivate(e);
      }
    });
  }

  /** Popover under the avatar with the user's name, email and role (loaded from api/me.php on first open). */
  function initAvatarPopover(avatar) {
    const name = el('p', { style: 'margin:0;font-size:14px;font-weight:700;color:#0f172a;', text: avatar.getAttribute('title') || '' });
    const email = el('p', { style: 'margin:4px 0 0;font-size:13px;color:#475569;word-break:break-all;', text: 'Loading…' });
    const role = el('p', { style: 'margin:8px 0 0;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b;' });
    const popover = el('div', {
      id: 'autocare-user-popover',
      role: 'dialog',
      'aria-label': 'Account details',
      hidden: true,
      style: 'position:absolute;top:calc(100% + 10px);right:0;min-width:220px;max-width:280px;padding:14px 16px;' +
        'background:#fff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 12px 28px rgba(15,23,42,.16);z-index:1000;text-align:left;',
    }, name, email, role);

    const anchor = avatar.parentElement;
    if (getComputedStyle(anchor).position === 'static') anchor.style.position = 'relative';
    anchor.appendChild(popover);

    let loaded = false;
    async function load() {
      if (loaded) return;
      loaded = true;
      try {
        const me = await createApi('../../api/').get('me.php');
        name.textContent = me.name;
        email.textContent = me.email || '—';
        role.textContent = me.role === 'VehicleOwner' ? 'Vehicle Owner' : me.role;
      } catch (err) {
        loaded = false;
        email.textContent = 'Could not load account details.';
      }
    }

    const isOpen = () => !popover.hidden;
    function setOpen(open, returnFocus) {
      popover.hidden = !open;
      avatar.setAttribute('aria-expanded', String(open));
      if (open) load();
      else if (returnFocus) avatar.focus();
    }

    avatar.setAttribute('aria-haspopup', 'dialog');
    avatar.setAttribute('aria-controls', popover.id);
    avatar.setAttribute('aria-expanded', 'false');
    makeControl(avatar, 'button', 'Account details', () => setOpen(!isOpen()));
    document.addEventListener('click', (e) => {
      if (isOpen() && !popover.contains(e.target) && !avatar.contains(e.target)) setOpen(false);
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && isOpen()) setOpen(false, true);
    });
  }

  /**
   * Topbar bell, mail and avatar. topbar: { bellHref, bellLabel, mailHref, mailLabel, hasUnread: async () => bool }.
   * The bell's red dot stays hidden unless hasUnread() resolves true.
   */
  function initTopbar(topbar) {
    const bell = document.querySelector('.topbar .notification');
    const mail = document.querySelector('.topbar .mail');
    const avatar = document.querySelector('.topbar .avatar');

    if (bell && topbar.bellHref) {
      makeControl(bell, 'link', topbar.bellLabel || 'Notifications', () => { window.location.href = topbar.bellHref; });
      const dot = bell.querySelector('b');
      if (dot) {
        dot.hidden = true;
        if (topbar.hasUnread) {
          Promise.resolve().then(topbar.hasUnread).then((unread) => {
            dot.hidden = !unread;
            if (unread) bell.setAttribute('aria-label', (topbar.bellLabel || 'Notifications') + ' (new)');
          }).catch(() => { /* leave the dot hidden */ });
        }
      }
    }
    if (mail && topbar.mailHref) {
      makeControl(mail, 'link', topbar.mailLabel || 'Messages', () => { window.location.href = topbar.mailHref; });
    }
    if (avatar) initAvatarPopover(avatar);
  }

  /** Common panel chrome: active nav item, topbar controls, logout confirmation, flash toast. */
  function initPanelChrome(panelName, topbar) {
    showFlash();
    if (topbar) initTopbar(topbar);

    const current = window.location.pathname.split('/').pop().toLowerCase();
    document.querySelectorAll('.sidebar .nav .nav-item').forEach((item) => {
      const href = (item.getAttribute('href') || '').split('/').pop().toLowerCase();
      item.classList.toggle('active', href === current);
    });

    const logout = document.querySelector('.sidebar .logout');
    if (logout) {
      logout.addEventListener('click', (e) => {
        e.preventDefault();
        openModal({
          title: 'Sign Out',
          content: el('p', { style: 'margin:0;', text: 'Are you sure you want to log out of the AutoCare ' + panelName + '?' }),
          confirmText: 'Sign Out',
          cancelText: 'Stay',
          onConfirm: () => { window.location.href = logout.getAttribute('href'); },
        });
      });
    }
  }

  window.AutoCare = {
    escapeHtml, el, icon, field, inputStyle, formatMoney, formatDate, formatTime, initials,
    showToast, openModal, createApi, withBusy, initPanelChrome, reloadWithToast,
  };
})();
