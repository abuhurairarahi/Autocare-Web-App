/**
 * vehicleowner-vehicles.js
 * "Add Vehicle" modal. The vehicle cards are rendered server-side.
 */
(function () {
  'use strict';
  const { el, field, inputStyle, openModal, showToast, reloadWithToast } = AutoCare;

  document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('vo-add-vehicle');
    if (addBtn) addBtn.addEventListener('click', openAddVehicleModal);
  });

  function openAddVehicleModal() {
    const maxYear = new Date().getFullYear() + 1;
    const make = el('input', { type: 'text', maxlength: '50', required: true, placeholder: 'e.g. Toyota', style: inputStyle });
    const model = el('input', { type: 'text', maxlength: '50', required: true, placeholder: 'e.g. Corolla', style: inputStyle });
    const year = el('input', { type: 'number', min: '1950', max: String(maxYear), required: true, placeholder: String(maxYear - 1), style: inputStyle });
    const plate = el('input', { type: 'text', maxlength: '20', required: true, placeholder: 'e.g. DHA-11-2345', style: inputStyle });
    const vin = el('input', { type: 'text', maxlength: '17', placeholder: 'Optional, 11-17 characters', style: inputStyle });
    const error = el('p', { role: 'alert', style: 'margin:0;color:#b91c1c;font-size:13px;' });

    const form = el('div', null,
      el('div', { style: 'display:grid;grid-template-columns:1fr 1fr;gap:0 14px;' },
        field('Make *', make), field('Model *', model),
        field('Year *', year), field('License Plate *', plate)),
      field('VIN', vin),
      error
    );

    openModal({
      title: 'Add Vehicle',
      content: form,
      confirmText: 'Add Vehicle',
      onConfirm: async () => {
        const payload = {
          make: make.value.trim(),
          model: model.value.trim(),
          year: Number(year.value),
          license_plate: plate.value.trim(),
          vin: vin.value.trim(),
        };

        const problem = validate(payload, maxYear);
        if (problem) {
          error.textContent = problem;
          return false;
        }

        try {
          await ownerApi.addVehicle(payload);
          reloadWithToast('Vehicle added to your fleet.');
        } catch (err) {
          error.textContent = err.message;
          showToast(err.message, 'error');
          return false;
        }
      },
    });
  }

  /** Mirrors the server-side checks so most mistakes are caught before a round trip. */
  function validate(v, maxYear) {
    if (!v.make || !v.model) return 'Make and model are required.';
    if (!Number.isInteger(v.year) || v.year < 1950 || v.year > maxYear) return 'Year must be between 1950 and ' + maxYear + '.';
    if (!/^[A-Za-z0-9][A-Za-z0-9 \-]{1,19}$/.test(v.license_plate)) return 'License plate must be 2-20 letters, numbers, spaces or dashes.';
    if (v.vin && !/^[A-HJ-NPR-Z0-9]{11,17}$/i.test(v.vin)) return 'VIN must be 11-17 letters/numbers (no I, O or Q).';
    return '';
  }
})();
