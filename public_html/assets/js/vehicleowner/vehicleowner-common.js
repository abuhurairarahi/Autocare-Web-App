/**
 * vehicleowner-common.js
 * Vehicle Owner panel bootstrap and API client. Requires shared/autocare-core.js.
 */
(function () {
  'use strict';

  const api = AutoCare.createApi('../../api/vehicleowner/');

  window.ownerApi = {
    request: api.request,
    getDashboard: () => api.get('dashboard.php'),
    getVehicles: () => api.get('vehicles.php'),
    addVehicle: (vehicle) => api.post('vehicles.php', vehicle),
    getAppointments: () => api.get('appointments.php'),
    bookAppointment: (booking) => api.post('appointments.php', booking),
    getRepairTracking: (jobId) => api.get('repair-tracking.php' + (jobId ? '?job_id=' + encodeURIComponent(jobId) : '')),
    getInvoices: (filters) => api.get('invoices.php?' + new URLSearchParams(filters || {}).toString()),
    getInvoice: (id) => api.get('invoices.php?id=' + encodeURIComponent(id)),
    getEstimates: () => api.get('estimates.php'),
    approveEstimate: (estimateId) => api.post('estimates.php', { action: 'approve', estimate_id: estimateId }),
    requestClarification: (estimateId, message) => api.post('estimates.php', { action: 'clarify', estimate_id: estimateId, message: message }),
    getServiceHistory: () => api.get('service-history.php'),
    getChat: (contactId) => api.get('chat.php' + (contactId ? '?contact_id=' + encodeURIComponent(contactId) : '')),
    sendChat: (receiverId, message) => api.post('chat.php', { receiver_id: receiverId, message: message }),
    getNotifications: () => api.get('notifications.php'),
    markAllRead: () => api.post('notifications.php', { action: 'mark_all_read' }),
  };

  document.addEventListener('DOMContentLoaded', () => AutoCare.initPanelChrome('Owner Portal'));
})();
