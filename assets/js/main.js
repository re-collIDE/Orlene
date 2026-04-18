// Booking fee live calculator
const startInput = document.getElementById('start_date');
const endInput   = document.getElementById('end_date');
const feeDisplay = document.getElementById('fee_display');
const daysDisplay = document.getElementById('days_display');
const dailyRate  = parseFloat(document.getElementById('daily_rate')?.value || 0);

function calcFee() {
  if (!startInput || !endInput || !dailyRate) return;
  const start = new Date(startInput.value);
  const end   = new Date(endInput.value);
  if (isNaN(start) || isNaN(end) || end <= start) return;
  const days = Math.round((end - start) / 86400000);
  if (days > 15) {
    const dateError = document.getElementById('date_error');
    if (dateError) dateError.textContent = 'Maximum rental period is 15 days.';
    const submitBtn = document.querySelector('button[type=submit]');
    if (submitBtn) submitBtn.disabled = true;
    return;
  }
  const dateError = document.getElementById('date_error');
  if (dateError) dateError.textContent = '';
  const submitBtn = document.querySelector('button[type=submit]');
  if (submitBtn) submitBtn.disabled = false;
  if (daysDisplay) daysDisplay.textContent = days;
  if (feeDisplay)  feeDisplay.textContent  = (days * dailyRate).toFixed(2);
  // Update end_date min
  const minEnd = new Date(start);
  minEnd.setDate(minEnd.getDate() + 1);
  endInput.min = minEnd.toISOString().split('T')[0];
  // Update end_date max
  const maxEnd = new Date(start);
  maxEnd.setDate(maxEnd.getDate() + 15);
  endInput.max = maxEnd.toISOString().split('T')[0];
}

if (startInput) startInput.addEventListener('change', calcFee);
if (endInput)   endInput.addEventListener('change', calcFee);

// Geolocation
const geoBtn = document.getElementById('use_location_btn');
if (geoBtn) {
  geoBtn.addEventListener('click', function() {
    if (!navigator.geolocation) {
      alert('Geolocation not supported by your browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(function(pos) {
      const latInput = document.getElementById('use_lat');
      const lngInput = document.getElementById('use_lng');
      if (latInput) latInput.value = pos.coords.latitude.toFixed(7);
      if (lngInput) lngInput.value = pos.coords.longitude.toFixed(7);
      geoBtn.textContent = 'Location captured';
      geoBtn.style.background = 'var(--color-success)';
      geoBtn.style.color = '#fff';
    }, function() {
      alert('Could not get your location. Please enter area manually.');
    });
  });
}

// SOS Modal
const sosBtn   = document.getElementById('sos_btn');
const sosModal = document.getElementById('sos_modal');
const sosClose = document.getElementById('sos_cancel');

if (sosBtn && sosModal) {
  sosBtn.addEventListener('click', () => sosModal.style.display = 'flex');
}
if (sosClose && sosModal) {
  sosClose.addEventListener('click', () => sosModal.style.display = 'none');
}

// Flash message auto-hide (optional)
document.addEventListener('DOMContentLoaded', () => {
  const alerts = document.querySelectorAll('.alert');
  alerts.forEach(alert => {
    setTimeout(() => {
      alert.style.opacity = '0';
      alert.style.transition = 'opacity 0.5s ease';
      setTimeout(() => alert.remove(), 500);
    }, 5000);
  });
});
