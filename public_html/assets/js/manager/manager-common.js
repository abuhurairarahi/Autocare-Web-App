/**
 * manager-common.js
 * Common interactions, notifications, modal management, topbar search & navigation
 * for AutoCare Workshop Manager Panel.
 */

document.addEventListener('DOMContentLoaded', () => {
  initSidebarHighlight();
  initTopbarSearch();
  initNotificationsDropdown();
  initGlobalModals();
  initCardHoverEffects();
  initLogoutHandler();
});

/**
 * 1. Highlight active navigation item based on current page URL
 */
function initSidebarHighlight() {
  const currentPath = window.location.pathname.toLowerCase();
  const navItems = document.querySelectorAll('.sidebar .nav .nav-item');

  navItems.forEach(item => {
    const href = item.getAttribute('href');
    if (!href) return;
    const itemPath = href.toLowerCase();
    
    // Check exact or partial match with filename
    const currentFilename = currentPath.substring(currentPath.lastIndexOf('/') + 1);
    const itemFilename = itemPath.substring(itemPath.lastIndexOf('/') + 1);

    if (currentFilename && itemFilename && currentFilename === itemFilename) {
      navItems.forEach(n => n.classList.remove('active'));
      item.classList.add('active');
    }
  });
}

/**
 * 2. Topbar Universal Search across bookings, job cards, mechanics, invoices
 */
function initTopbarSearch() {
  const searchInput = document.querySelector('.topbar .search input');
  if (!searchInput) return;

  // Create search dropdown container if not present
  let resultsContainer = document.getElementById('topbar-search-results');
  if (!resultsContainer) {
    resultsContainer = document.createElement('div');
    resultsContainer.id = 'topbar-search-results';
    resultsContainer.style.cssText = `
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: #ffffff;
      border-radius: 8px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
      max-height: 380px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      margin-top: 6px;
    `;
    const searchWrapper = searchInput.closest('.search');
    if (searchWrapper) {
      searchWrapper.style.position = 'relative';
      searchWrapper.appendChild(resultsContainer);
    }
  }

  searchInput.addEventListener('input', (e) => {
    const query = e.target.value.trim().toLowerCase();
    if (!query || query.length < 2) {
      resultsContainer.style.display = 'none';
      return;
    }

    if (typeof AutoCareStore === 'undefined') return;

    const jobCards = AutoCareStore.getJobCards().filter(j => 
      j.code.toLowerCase().includes(query) ||
      (j.customer_name && j.customer_name.toLowerCase().includes(query)) ||
      (j.vehicle_details && j.vehicle_details.toLowerCase().includes(query))
    );

    const mechanics = AutoCareStore.getMechanics().filter(m => 
      m.name.toLowerCase().includes(query) ||
      m.specialty.toLowerCase().includes(query)
    );

    const invoices = AutoCareStore.getInvoices().filter(i => 
      i.invoice_number.toLowerCase().includes(query) ||
      i.customer_name.toLowerCase().includes(query)
    );

    let html = '';

    if (jobCards.length > 0) {
      html += `<div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">JOB CARDS</div>`;
      jobCards.slice(0, 3).forEach(j => {
        html += `
          <a href="manager-jobCards.html" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: #1e293b;">
            <div>
              <strong style="color: #2563eb;">${j.code}</strong> - <span>${j.customer_name}</span>
              <div style="font-size: 12px; color: #64748b;">${j.vehicle_title || j.vehicle_details}</div>
            </div>
            <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #e0f2fe; color: #0284c7; font-weight: 600;">${j.status}</span>
          </a>
        `;
      });
    }

    if (mechanics.length > 0) {
      html += `<div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">MECHANICS</div>`;
      mechanics.slice(0, 3).forEach(m => {
        html += `
          <a href="manager-mechanics.html" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: #1e293b;">
            <div>
              <strong>${m.name}</strong>
              <div style="font-size: 12px; color: #64748b;">${m.specialty}</div>
            </div>
            <span style="font-size: 11px; padding: 2px 8px; border-radius: 999px; background: #dcfce7; color: #16a34a; font-weight: 600;">${m.status}</span>
          </a>
        `;
      });
    }

    if (invoices.length > 0) {
      html += `<div style="padding: 8px 12px; font-size: 11px; font-weight: 700; color: #64748b; background: #f8fafc; border-bottom: 1px solid #e2e8f0;">INVOICES</div>`;
      invoices.slice(0, 3).forEach(i => {
        html += `
          <a href="manager-invoice-management.html" style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: #1e293b;">
            <div>
              <strong style="color: #0f172a;">${i.invoice_number}</strong>
              <div style="font-size: 12px; color: #64748b;">${i.customer_name}</div>
            </div>
            <strong style="color: #16a34a;">৳${i.total_amount.toLocaleString()}</strong>
          </a>
        `;
      });
    }

    if (!html) {
      html = `<div style="padding: 16px; text-align: center; color: #94a3b8; font-size: 13px;">No matching records found</div>`;
    }

    resultsContainer.innerHTML = html;
    resultsContainer.style.display = 'block';
  });

  // Close when clicking outside
  document.addEventListener('click', (e) => {
    if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
      resultsContainer.style.display = 'none';
    }
  });
}

/**
 * 3. Notification bell dropdown
 */
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
  const notices = typeof AutoCareStore !== 'undefined' ? AutoCareStore.getNotices() : [];
  const activities = typeof AutoCareStore !== 'undefined' ? AutoCareStore.getRecentActivity().slice(0, 3) : [];

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
      container.style.display = 'none';
      
      const allNotices = typeof AutoCareStore !== 'undefined' ? AutoCareStore.getNotices() : [];
      const allActivities = typeof AutoCareStore !== 'undefined' ? AutoCareStore.getRecentActivity() : [];

      let fullContent = '<div style="max-height: 400px; overflow-y: auto; padding-right: 8px;">';
      
      fullContent += '<h4 style="margin-top:0; color:#334155;">Alerts & Notices</h4>';
      if(allNotices.length === 0) fullContent += '<p style="color:#64748b; font-size:13px;">No new alerts.</p>';
      allNotices.forEach(n => {
        fullContent += `
          <div style="padding: 12px 16px; border: 1px solid #f1f5f9; border-left: 4px solid #f59e0b; background: #fffbeb; border-radius: 6px; margin-bottom: 8px;">
            <div style="font-size: 14px; font-weight: 700; color: #92400e;">⚠️ ${n.title}</div>
            <div style="font-size: 13px; color: #b45309; margin-top: 4px;">${n.content}</div>
            <div style="font-size: 11px; color: #d97706; margin-top: 6px;">${n.created_at}</div>
          </div>
        `;
      });

      fullContent += '<h4 style="margin-top:20px; color:#334155;">Recent Activities</h4>';
      if(allActivities.length === 0) fullContent += '<p style="color:#64748b; font-size:13px;">No recent activities.</p>';
      allActivities.forEach(a => {
        fullContent += `
          <div style="padding: 12px 16px; border: 1px solid #e2e8f0; border-left: 4px solid #3b82f6; background: #f8fafc; border-radius: 6px; margin-bottom: 8px;">
            <div style="font-size: 13px; color: #334155; font-weight: 500;">${a.text}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 6px;">${a.time} ${a.subtext ? '• ' + a.subtext : ''}</div>
          </div>
        `;
      });
      fullContent += '</div>';
      
      if(typeof openModal === 'function') {
          openModal({
            title: 'Activity Dashboard',
            content: fullContent,
            confirmText: 'Done',
            cancelText: 'Close'
          });
      }
    });
  }
}

/**
 * 4. Sleek Toast Notification System
 */
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
  toast.innerHTML = `
    <span style="display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; background: rgba(255,255,255,0.25); border-radius: 50%; font-size: 12px;">${icon}</span>
    <span>${message}</span>
  `;

  toastContainer.appendChild(toast);

  // Animate in
  requestAnimationFrame(() => {
    toast.style.transform = 'translateX(0)';
    toast.style.opacity = '1';
  });

  // Auto dismiss
  setTimeout(() => {
    toast.style.transform = 'translateX(120%)';
    toast.style.opacity = '0';
    setTimeout(() => {
      if (toast.parentNode) toast.parentNode.removeChild(toast);
    }, 400);
  }, duration);
};

/**
 * 5. Global Modal Dialog System
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
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
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

function initGlobalModals() {
  // Attached to window.openModal
}

/**
 * 6. Hover animations for cards and action buttons
 */
function initCardHoverEffects() {
  const cards = document.querySelectorAll('.stat-card, .kpi-card, .panel, .mechanic-card');
  cards.forEach(card => {
    card.style.transition = 'transform 0.25s ease, box-shadow 0.25s ease';
    card.addEventListener('mouseenter', () => {
      card.style.transform = 'translateY(-3px)';
    });
    card.addEventListener('mouseleave', () => {
      card.style.transform = 'translateY(0)';
    });
  });
}

/**
 * 7. Logout handler with confirmation
 */
function initLogoutHandler() {
  const logoutBtn = document.querySelector('.sidebar .logout');
  if (!logoutBtn) return;

  logoutBtn.addEventListener('click', (e) => {
    e.preventDefault();
    openModal({
      title: 'Sign Out',
      content: 'Are you sure you want to log out of the AutoCare Manager Panel?',
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

// Global API helper for Manager Pages
window.managerApi = {
  request: async function(endpoint, method = 'GET', data = null) {
    try {
      const options = { method };
      if (data) {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(data);
      }
      const response = await fetch(`../../api/manager/${endpoint}`, options);
      const result = await response.json();
      if (!result.success) {
        console.error('Manager API Error:', result.error || 'Unknown error');
        return null;
      }
      return result.data !== undefined ? result.data : result;
    } catch (err) {
      console.error('Manager Network Error:', err);
      return null;
    }
  },
  getDashboard: () => window.managerApi.request('dashboard.php', 'GET'),
  createJobCard: (data) => window.managerApi.request('job-cards.php', 'POST', Object.assign({ action: 'create' }, data)),
  updateJobCardStatus: (jobId, status, kanbanStage, progress) => window.managerApi.request('job-cards.php', 'POST', { action: 'update_status', job_id: jobId, status: status, kanban_stage: kanbanStage, progress_percentage: progress }),
  assignJobCardMechanic: (jobId, mechanicId) => window.managerApi.request('job-cards.php', 'POST', { action: 'assign_mechanic', job_id: jobId, mechanic_id: mechanicId }),
  getBookingRequests: (status, priority) => window.managerApi.request(`booking-requests.php?status=${status || 'all'}&priority=${priority || 'all'}`),
  approveBookingRequest: (appointmentId, mechanicId, estimatedCost, scope) => window.managerApi.request('booking-requests.php', 'POST', { action: 'approve', appointment_id: appointmentId, mechanic_id: mechanicId, estimated_cost: estimatedCost, service_scope: scope }),
  rejectBookingRequest: (appointmentId, reason) => window.managerApi.request('booking-requests.php', 'POST', { action: 'reject', appointment_id: appointmentId, reason: reason }),
  getJobCards: (filter) => window.managerApi.request(`job-cards.php?filter=${filter || 'all'}`),
  getMechanics: (specialty, status) => window.managerApi.request(`mechanics.php?specialty=${specialty || 'all'}&status=${status || 'all'}`),
  getProcessTracker: () => window.managerApi.request('process-tracker.php'),
  updateKanbanStage: (cardId, targetStage, status) => window.managerApi.request('process-tracker.php', 'POST', { card_id: cardId, target_stage: targetStage, status: status }),
  getEstimates: (id) => window.managerApi.request(`cost-estimation.php${id ? '?id=' + id : ''}`),
  saveEstimate: (data) => window.managerApi.request('cost-estimation.php', 'POST', data),
  getInvoices: (filter) => window.managerApi.request(`invoice-management.php?filter=${filter || 'all'}`),
  createInvoice: (data) => window.managerApi.request('invoice-management.php', 'POST', Object.assign({ action: 'create' }, data)),
  updateInvoiceStatus: (invoiceId, status) => window.managerApi.request('invoice-management.php', 'POST', { action: 'update_status', invoice_id: invoiceId, status: status }),
  getSparePartsRequests: (status, search) => window.managerApi.request(`payment-approval.php?status=${status || 'All'}&search=${encodeURIComponent(search || '')}`),
  approveSparePart: (reqId) => window.managerApi.request('payment-approval.php', 'POST', { action: 'approve', request_id: reqId }),
  rejectSparePart: (reqId, reason) => window.managerApi.request('payment-approval.php', 'POST', { action: 'reject', request_id: reqId, reason: reason }),
  approveAllSpareParts: () => window.managerApi.request('payment-approval.php', 'POST', { action: 'approve_all' }),
  createSparePartRequest: (data) => window.managerApi.request('payment-approval.php', 'POST', Object.assign({ action: 'create_request' }, data)),
  getChat: (userId) => window.managerApi.request(`chat.php?user_id=${userId || 8}`),
  sendChat: (data) => window.managerApi.request('chat.php', 'POST', data),
  getPerformance: (period) => window.managerApi.request(`performance.php?period=${period || '30'}`),
  getStoreSync: () => window.managerApi.request('store-sync.php')
};

// Seamless Navigation Helper: Ensures links stay consistent between .html and .php
(function() {
  const isPhpPage = window.location.pathname.endsWith('.php');
  if (isPhpPage) {
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('a[href*="manager-"]').forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.endsWith('.html')) {
          link.setAttribute('href', href.replace(/\.html$/, '.php'));
        }
      });
    });

    document.addEventListener('click', (e) => {
      const anchor = e.target.closest('a');
      if (anchor) {
        const href = anchor.getAttribute('href');
        if (href && href.includes('manager-') && href.endsWith('.html')) {
          e.preventDefault();
          window.location.href = href.replace(/\.html$/, '.php');
        }
      }
    }, true);
  }
})();


