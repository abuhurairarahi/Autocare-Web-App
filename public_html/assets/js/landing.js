document.addEventListener('DOMContentLoaded', () => {
  fetchLandingData();
});

async function fetchLandingData() {
  try {
    const response = await fetch('public_html/api/landing-api.php?action=data');
    const data = await response.json();
    
    if (data.success) {
      renderServices(data.services);
      renderLocations(data.locations);
      populateServiceSelect(data.services);
    }
  } catch (error) {
    console.error('Error fetching landing data:', error);
  }
}

function renderServices(services) {
  const grid = document.getElementById('services-grid');
  if (!grid) return;
  
  let html = '';
  services.forEach(s => {
    html += `
      <article class="service-card">
        <div class="service-top">
          <span class="icon-box"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 8.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 0 0 0-7z" stroke="white" stroke-width="1.6"/><path d="M12 3.5v2M12 18.5v2M20.5 12h-2M5.5 12h-2M17.8 6.2l-1.4 1.4M7.6 16.4l-1.4 1.4M17.8 17.8l-1.4-1.4M7.6 7.6L6.2 6.2" stroke="white" stroke-width="1.6" stroke-linecap="round"/></svg></span>
          <div class="service-price"><span class="amount">৳${parseFloat(s.price).toLocaleString()}</span><span class="starting">Starting</span></div>
        </div>
        <h3>${s.title}</h3>
        <p class="service-meta">${s.category} · ${s.duration}</p>
        <a class="service-link" href="#contact">Book this service <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M9 6l6 6-6 6" stroke="#0B1B3A" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      </article>
    `;
  });
  grid.innerHTML = html;
}

function renderLocations(locations) {
  const grid = document.getElementById('locations-grid');
  if (!grid) return;
  
  let html = '';
  locations.forEach(loc => {
    html += `
      <article class="location-card">
        <span class="pin-box">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" stroke="#FF6A24" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4" stroke="#FF6A24" stroke-width="1.6"/></svg>
        </span>
        <h3>${loc.name}</h3>
        <p class="loc-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 21s7-6.5 7-11.5A7 7 0 0 0 5 9.5C5 14.5 12 21 12 21z" stroke="#545E6F" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.2" stroke="#545E6F" stroke-width="1.6"/></svg>
          ${loc.address}
        </p>
        <p class="loc-row">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8" stroke="#545E6F" stroke-width="1.6"/><path d="M12 8v4l2.6 2.6" stroke="#545E6F" stroke-width="1.6" stroke-linecap="round"/></svg>
          ${loc.hours}
        </p>
        <a class="text-link" href="#">Get directions <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 6l6 6-6 6" stroke="#FB7C00" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
      </article>
    `;
  });
  grid.innerHTML = html;
}

function populateServiceSelect(services) {
  const select = document.getElementById('contact-service-select');
  if (!select) return;
  
  let html = '<option value="" selected disabled>Service Type</option>';
  services.forEach(s => {
    html += `<option value="${s.id}">${s.title}</option>`;
  });
  select.innerHTML = html;
}
