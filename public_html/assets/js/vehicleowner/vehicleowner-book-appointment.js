/**
 * vehicleowner-book-appointment.js
 * Submits the booking form to the API and keeps a local draft of the form.
 */
(function () {
  'use strict';
  const { showToast, reloadWithToast, withBusy } = AutoCare;
  const DRAFT_KEY = 'autocare-booking-draft';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('vo-booking-form');
    if (!form) return;

    restoreDraft(form);
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      submitBooking(form);
    });

    document.getElementById('vo-save-draft')?.addEventListener('click', () => {
      try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(readForm(form)));
        showToast('Draft saved on this device.', 'info');
      } catch (err) {
        showToast('Could not save a draft in this browser.', 'warning');
      }
    });

    // Appointments have no photo column, so photos are not uploaded with a booking
    document.getElementById('vo-photos')?.addEventListener('change', (e) => {
      if (e.target.files.length) {
        showToast('Photos are not attached to bookings yet. Please describe the issue in the details box.', 'warning', 5000);
        e.target.value = '';
      }
    });
  });

  function readForm(form) {
    const data = new FormData(form);
    return {
      vehicle_id: Number(data.get('vehicle_id')) || null,
      workshop_id: Number(data.get('workshop_id')) || null,
      service_category_id: Number(data.get('service_category_id')) || null,
      preferred_date: data.get('preferred_date') || '',
      time_slot: data.get('time_slot') || '',
      description: (data.get('description') || '').trim(),
    };
  }

  function validate(b) {
    if (!b.vehicle_id) return 'Select a vehicle. You can add one under My Vehicles.';
    if (!b.workshop_id) return 'Select a workshop.';
    if (!b.service_category_id) return 'Select a service type.';
    if (!b.preferred_date) return 'Choose a preferred date.';
    const today = new Date();
    const todayStr = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    if (b.preferred_date < todayStr) return 'Preferred date cannot be in the past.';
    if (b.description.length > 2000) return 'Additional details must be 2000 characters or fewer.';
    return '';
  }

  async function submitBooking(form) {
    const booking = readForm(form);
    const problem = validate(booking);
    if (problem) {
      showToast(problem, 'error');
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    await withBusy(submitBtn, async () => {
      try {
        const created = await ownerApi.bookAppointment(booking);
        try { localStorage.removeItem(DRAFT_KEY); } catch (err) { /* storage blocked */ }
        reloadWithToast('Appointment ' + created.code + ' requested. The workshop will confirm it shortly.');
      } catch (err) {
        showToast(err.message, 'error', 5000);
      }
    });
  }

  function restoreDraft(form) {
    let draft = null;
    try { draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (err) { return; }
    if (!draft) return;

    const setValue = (name, value) => {
      const control = form.elements[name];
      if (!control || value == null || value === '') return;
      if (control instanceof RadioNodeList) {
        Array.from(control).forEach((radio) => { radio.checked = radio.value === String(value); });
      } else if (Array.from(control.options || []).some((o) => o.value === String(value)) || !control.options) {
        control.value = value;
      }
    };
    // A vehicle chosen via ?vehicle_id= wins over the draft's vehicle
    if (!new URLSearchParams(window.location.search).has('vehicle_id')) setValue('vehicle_id', draft.vehicle_id);
    ['workshop_id', 'service_category_id', 'preferred_date', 'time_slot', 'description'].forEach((k) => setValue(k, draft[k]));
    showToast('Restored your saved draft.', 'info');
  }
})();
