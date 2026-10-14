/**
 * mechanic-common.js
 * Mechanic panel bootstrap and API client. Requires shared/autocare-core.js.
 */
(function () {
  'use strict';

  const api = AutoCare.createApi('../../api/mechanic/');

  window.mechanicApi = {
    request: api.request,
    getDashboard: () => api.get('dashboard.php'),
    getJobs: (filters) => api.get('jobs.php?' + new URLSearchParams(filters || {}).toString()),
    getJob: (jobId) => api.get('jobs.php?id=' + encodeURIComponent(jobId)),
    updateJobStatus: (jobId, status) => api.post('jobs.php', { action: 'update_status', job_id: jobId, status: status }),
    getPartsLabor: (jobId) => api.get('parts-labor.php?job_id=' + encodeURIComponent(jobId)),
    addPart: (jobId, partId, quantity) => api.post('parts-labor.php', { action: 'add_part', job_id: jobId, part_id: partId, quantity: quantity }),
    removePart: (jobPartId) => api.post('parts-labor.php', { action: 'remove_part', job_part_id: jobPartId }),
    addLabor: (labor) => api.post('parts-labor.php', Object.assign({ action: 'add_labor' }, labor)),
    removeLabor: (laborId) => api.post('parts-labor.php', { action: 'remove_labor', labor_id: laborId }),
    notifyManager: (jobId, topic) => api.post('jobs.php', { action: 'notify_manager', job_id: jobId, topic: topic }),
    getPhotos: (jobId) => api.get('photos.php?job_id=' + encodeURIComponent(jobId)),
    uploadPhoto: (formData) => api.post('photos.php', formData),
    deletePhoto: (photoId) => api.post('photos.php', { action: 'delete', photo_id: photoId }),
    getFaultReports: (jobId) => api.get('fault-report.php' + (jobId ? '?job_id=' + encodeURIComponent(jobId) : '')),
    submitFaultReport: (report) => api.post('fault-report.php', report),
    getChat: (contactId) => api.get('chat.php' + (contactId ? '?contact_id=' + encodeURIComponent(contactId) : '')),
    sendChat: (receiverId, message) => api.post('chat.php', { receiver_id: receiverId, message: message }),
  };

  document.addEventListener('DOMContentLoaded', () => AutoCare.initPanelChrome('Mechanic Panel', {
    bellHref: 'mechanic-assigned-jobs.php',
    bellLabel: 'Assigned jobs',
    mailHref: 'mechanic-chat.php',
    mailLabel: 'Messages',
    // "New" = assigned jobs the mechanic has not started yet.
    hasUnread: () => api.get('dashboard.php').then((d) => d.not_started > 0),
  }));
})();
