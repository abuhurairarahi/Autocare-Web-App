/**
 * admin.js
 * Common interactions, notifications, modal management, topbar search & navigation
 * for AutoCare Admin Panel.
 */

document.addEventListener('DOMContentLoaded', () => {
  initSidebarHighlight();
  initLogoutHandler();
});

/**
 * Highlight active navigation item based on current page URL
 */
function initSidebarHighlight() {
  const currentPath = window.location.pathname.toLowerCase();
  const navItems = document.querySelectorAll('.sidebar .nav .nav-item');

  navItems.forEach(item => {
    const href = item.getAttribute('href');
    if (!href) return;
    const itemPath = href.toLowerCase();
    
    const currentFilename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    const itemFilename = itemPath.substring(itemPath.lastIndexOf('/') + 1);

    if (currentFilename && itemFilename && currentFilename === itemFilename) {
      navItems.forEach(n => n.classList.remove('active'));
      item.classList.add('active');
    }
  });
}

/**
 * Global Modal Dialog System
 */
window.openModal = function (options = {}) {
  const { title = 'Confirm Action', content = '', confirmText = 'Confirm', cancelText = 'Cancel', onConfirm, onCancel } = options;

  let modalOverlay = document.getElementById('autocare-modal-overlay');
  if (modalOverlay) {
    modalOverlay.remove();
  }

  modalOverlay = document.createElement('div');
  modalOverlay.id = 'autocare-modal-overlay';
  modalOverlay.style.cssText = `
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    z-index: 99998; opacity: 0; transition: opacity 0.25s ease;
  `;

  const modalBox = document.createElement('div');
  modalBox.style.cssText = `
    background: #ffffff; border-radius: 14px; width: 90%; max-width: 520px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25); overflow: hidden;
    transform: scale(0.92); transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  `;

  modalBox.innerHTML = `
    <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
      <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">${title}</h3>
      <button id="modal-close-x" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;">&times;</button>
    </div>
    <div style="padding: 24px; font-size: 14px; color: #334155; line-height: 1.6;">${content}</div>
    <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;">
      <button id="modal-cancel-btn" style="padding: 9px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; cursor: pointer;">${cancelText}</button>
      <button id="modal-confirm-btn" style="padding: 9px 20px; border-radius: 8px; border: none; background: #2563eb; color: #ffffff; font-weight: 600; cursor: pointer;">${confirmText}</button>
    </div>
  `;

  modalOverlay.appendChild(modalBox);
  document.body.appendChild(modalOverlay);

  requestAnimationFrame(() => {
    modalOverlay.style.opacity = '1';
    modalBox.style.transform = 'scale(1)';
  });

  const close = () => {
    modalOverlay.style.opacity = '0';
    modalBox.style.transform = 'scale(0.92)';
    setTimeout(() => { if (modalOverlay.parentNode) modalOverlay.parentNode.removeChild(modalOverlay); }, 250);
  };

  modalBox.querySelector('#modal-close-x').addEventListener('click', () => { if (onCancel) onCancel(); close(); });
  modalBox.querySelector('#modal-cancel-btn').addEventListener('click', () => { if (onCancel) onCancel(); close(); });
  modalBox.querySelector('#modal-confirm-btn').addEventListener('click', () => { if (onConfirm) onConfirm(); close(); });
  modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) { if (onCancel) onCancel(); close(); } });
};

/**
 * Sleek Toast Notification System
 */
window.showToast = function (message, type = 'success', duration = 3500) {
  let toastContainer = document.getElementById('autocare-toast-container');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'autocare-toast-container';
    toastContainer.style.cssText = `
      position: fixed; top: 24px; right: 24px; z-index: 99999;
      display: flex; flex-direction: column; gap: 10px; pointer-events: none;
    `;
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  toast.style.cssText = `
    display: flex; align-items: center; gap: 12px; padding: 14px 20px; border-radius: 10px;
    color: #ffffff; font-size: 13.5px; font-weight: 600; box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    pointer-events: auto; transform: translateX(120%); opacity: 0;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease; backdrop-filter: blur(8px);
  `;

  let bgColor = '#10b981'; // success green
  let icon = '✓';

  if (type === 'error' || type === 'danger') { bgColor = '#ef4444'; icon = '✕'; }
  else if (type === 'warning') { bgColor = '#f59e0b'; icon = '⚠️'; }
  else if (type === 'info') { bgColor = '#3b82f6'; icon = 'ℹ'; }

  toast.style.backgroundColor = bgColor;
  toast.innerHTML = \`
    <span style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: rgba(255,255,255,0.25); border-radius: 50%; font-size: 12px;">\${icon}</span>
    <span>\${message}</span>
  \`;

  toastContainer.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.transform = 'translateX(0)';
    toast.style.opacity = '1';
  });

  setTimeout(() => {
    toast.style.transform = 'translateX(120%)';
    toast.style.opacity = '0';
    setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 400);
  }, duration);
};

/**
 * Logout handler with confirmation
 */
function initLogoutHandler() {
  const logoutBtn = document.querySelector('.sidebar .logout');
  if (!logoutBtn) return;

  logoutBtn.addEventListener('click', (e) => {
    e.preventDefault();
    openModal({
      title: 'Sign Out',
      content: 'Are you sure you want to log out of the AutoCare Admin Panel?',
      confirmText: 'Sign Out',
      cancelText: 'Stay',
      onConfirm: () => {
        showToast('Logging out...', 'info');
        setTimeout(() => {
          window.location.href = '../login.html';
        }, 600);
      }
    });
  });
}
