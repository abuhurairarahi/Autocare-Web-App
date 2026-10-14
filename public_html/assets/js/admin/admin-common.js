/**
 * admin-common.js
 * Common interactions, notifications, modal management, and logout
 * for AutoCare Admin Panel.
 */

function initAdminCommon() {
  initLogoutHandler();
  initNotificationsDropdown();
  initMailIcon();
}

function initMailIcon() {
  const mailIcon = document.querySelector('.topbar .mail');
  if (mailIcon) {
    mailIcon.style.cursor = 'pointer';
    mailIcon.addEventListener('click', () => {
      window.location.href = 'service-broadcast.html';
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdminCommon);
} else {
  initAdminCommon();
}

// Global API helper for Admin Pages
window.adminApi = {
  request: async function(endpoint, method = 'GET', data = null) {
    try {
      const options = { method };
      if (data) {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(data);
      }
      const response = await fetch(`../../api/admin/${endpoint}`, options);
      const result = await response.json();
      if (!result.success) {
        console.error('API Error:', result.error || 'Unknown error');
        return null;
      }
      return result.data || result;
    } catch (err) {
      console.error('Network Error:', err);
      return null;
    }
  }
};

window.showToast = function (message, type = 'success', duration = 3500) {
  let toastContainer = document.getElementById('autocare-toast-container');
  if (!toastContainer) {
    toastContainer = document.createElement('div');
    toastContainer.id = 'autocare-toast-container';
    toastContainer.style.cssText = `
      position: fixed;
      top: 24px;
      right: 24px;
      z-index: 99999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      pointer-events: none;
    `;
    document.body.appendChild(toastContainer);
  }

  const toast = document.createElement('div');
  toast.style.cssText = `
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 20px;
    border-radius: 10px;
    color: #ffffff;
    font-size: 13.5px;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    pointer-events: auto;
    transform: translateX(120%);
    opacity: 0;
    transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.35s ease;
    backdrop-filter: blur(8px);
  `;

  let bgColor = '#10b981'; // success green
  let icon = '✓';

  if (type === 'error' || type === 'danger') {
    bgColor = '#ef4444';
    icon = '✕';
  } else if (type === 'warning') {
    bgColor = '#f59e0b';
    icon = '⚠️';
  } else if (type === 'info') {
    bgColor = '#3b82f6';
    icon = 'ℹ';
  }

  toast.style.backgroundColor = bgColor;
  toast.innerHTML = `<span style="font-size: 16px;">${icon}</span><span>${message}</span>`;
  toastContainer.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.transform = 'translateX(0)';
    toast.style.opacity = '1';
  });

  setTimeout(() => {
    toast.style.transform = 'translateX(120%)';
    toast.style.opacity = '0';
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 400);
  }, duration);
};

window.openModal = function ({ title, content, confirmText = 'Confirm', cancelText = 'Cancel', onConfirm, onCancel }) {
  const modalOverlay = document.createElement('div');
  modalOverlay.style.cssText = `
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99998;
    opacity: 0;
    transition: opacity 0.25s ease;
  `;

  const modalBox = document.createElement('div');
  modalBox.style.cssText = `
    background: #ffffff;
    border-radius: 14px;
    width: 90%;
    max-width: 520px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
    overflow: hidden;
    transform: scale(0.92);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  `;

  modalBox.innerHTML = `
    <div style="padding: 18px 24px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
      <h3 style="margin: 0; font-size: 17px; font-weight: 700; color: #0f172a;">${title}</h3>
      <button id="modal-close-x" style="background: none; border: none; font-size: 22px; cursor: pointer; color: #64748b;">&times;</button>
    </div>
    <div style="padding: 24px; font-size: 14px; color: #334155; line-height: 1.6;">
      ${content}
    </div>
    <div style="padding: 16px 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 12px;">
      <button id="modal-cancel-btn" style="padding: 9px 18px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; font-weight: 600; cursor: pointer;">${cancelText}</button>
      <button id="modal-confirm-btn" style="padding: 9px 20px; border-radius: 8px; border: none; background: #2563eb; color: #ffffff; font-weight: 600; cursor: pointer;">${confirmText}</button>
    </div>
  `;

  modalOverlay.appendChild(modalBox);
  document.body.appendChild(modalOverlay);

  // Animate in
  requestAnimationFrame(() => {
    modalOverlay.style.opacity = '1';
    modalBox.style.transform = 'scale(1)';
  });

  const close = () => {
    modalOverlay.style.opacity = '0';
    modalBox.style.transform = 'scale(0.92)';
    setTimeout(() => {
      if (modalOverlay.parentNode) modalOverlay.parentNode.removeChild(modalOverlay);
    }, 250);
  };

  modalBox.querySelector('#modal-close-x').addEventListener('click', () => {
    if (onCancel) onCancel();
    close();
  });

  modalBox.querySelector('#modal-cancel-btn').addEventListener('click', () => {
    if (onCancel) onCancel();
    close();
  });

  modalBox.querySelector('#modal-confirm-btn').addEventListener('click', () => {
    if (onConfirm) onConfirm();
    close();
  });

  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) {
      if (onCancel) onCancel();
      close();
    }
  });
};

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

function initNotificationsDropdown() {
  const notifBtn = document.querySelector('.topbar .notification');
  if (!notifBtn) return;

  notifBtn.style.cursor = 'pointer';
  notifBtn.style.position = 'relative';

  const menu = document.createElement('div');
  menu.className = 'notif-dropdown-menu';
  menu.style.cssText = `
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 320px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.18);
    border: 1px solid #e2e8f0;
    z-index: 1000;
    display: none;
    overflow: hidden;
  `;

  notifBtn.appendChild(menu);

  notifBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isVisible = menu.style.display === 'block';
    
    // Close other dropdowns
    document.querySelectorAll('.notif-dropdown-menu').forEach(m => m.style.display = 'none');

    if (!isVisible) {
      renderNotificationItems(menu);
      menu.style.display = 'block';
    } else {
      menu.style.display = 'none';
    }
  });

  document.addEventListener('click', () => {
    menu.style.display = 'none';
  });
}

function renderNotificationItems(container) {
  // Use generic mock data for admin notifications if no store is present
  const notices = [
    { title: 'System Update', content: 'Scheduled maintenance this weekend.', created_at: '2 hours ago' },
    { title: 'New Registration', content: '5 new mechanics joined today.', created_at: '5 hours ago' }
  ];
  const activities = [
    { text: 'Invoice #INV-290 paid by customer.', time: '1 hour ago', subtext: '৳1,240.00' },
    { text: 'Workshop Elite Auto Care approved.', time: 'Yesterday', subtext: 'By Admin' }
  ];

  let html = `
    <div style="padding: 12px 16px; background: #0f172a; color: #ffffff; font-weight: 700; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
      <span>Notifications & Alerts</span>
      <span style="font-size: 11px; background: #ef4444; color: #fff; padding: 2px 6px; border-radius: 999px;">${notices.length + activities.length}</span>
    </div>
    <div style="max-height: 280px; overflow-y: auto;">
  `;

  notices.forEach(n => {
    html += `
      <div style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9; background: #fffbeb;">
        <div style="font-size: 13px; font-weight: 700; color: #92400e;">⚠️ ${n.title}</div>
        <div style="font-size: 12px; color: #b45309; margin-top: 2px;">${n.content}</div>
        <div style="font-size: 10px; color: #d97706; margin-top: 4px;">${n.created_at}</div>
      </div>
    `;
  });

  activities.forEach(a => {
    html += `
      <div style="padding: 12px 16px; border-bottom: 1px solid #f1f5f9;">
        <div style="font-size: 12px; color: #334155;">${a.text}</div>
        <div style="font-size: 10px; color: #94a3b8; margin-top: 4px;">${a.time} ${a.subtext ? '• ' + a.subtext : ''}</div>
      </div>
    `;
  });

  html += `
    </div>
    <div style="padding: 10px; text-align: center; background: #f8fafc; border-top: 1px solid #e2e8f0;">
      <a href="#" id="view-all-activities-link" style="font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 600;">View Activity Dashboard</a>
    </div>
  `;

  container.innerHTML = html;

  const viewAllBtn = container.querySelector('#view-all-activities-link');
  if (viewAllBtn) {
    viewAllBtn.addEventListener('click', (e) => {
      e.preventDefault();
      container.style.display = 'none'; // hide dropdown
      
      let fullContent = '<div style="max-height: 400px; overflow-y: auto; padding-right: 8px;">';
      
      fullContent += '<h4 style="margin-top:0; color:#334155;">Alerts & Notices</h4>';
      notices.forEach(n => {
        fullContent += `
          <div style="padding: 12px 16px; border: 1px solid #f1f5f9; border-left: 4px solid #f59e0b; background: #fffbeb; border-radius: 6px; margin-bottom: 8px;">
            <div style="font-size: 14px; font-weight: 700; color: #92400e;">⚠️ ${n.title}</div>
            <div style="font-size: 13px; color: #b45309; margin-top: 4px;">${n.content}</div>
            <div style="font-size: 11px; color: #d97706; margin-top: 6px;">${n.created_at}</div>
          </div>
        `;
      });

      fullContent += '<h4 style="margin-top:20px; color:#334155;">Recent Activities</h4>';
      activities.forEach(a => {
        fullContent += `
          <div style="padding: 12px 16px; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; background: #f8fafc; border-radius: 6px; margin-bottom: 8px;">
            <div style="font-size: 13px; color: #334155; font-weight: 500;">${a.text}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 6px;">${a.time} ${a.subtext ? '• ' + a.subtext : ''}</div>
          </div>
        `;
      });
      fullContent += '</div>';
      
      openModal({
        title: 'Activity Dashboard',
        content: fullContent,
        confirmText: 'Done',
        cancelText: 'Close'
      });
    });
  }
}
