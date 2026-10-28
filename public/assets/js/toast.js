let region;

function ensureRegion() {
  if (!region) {
    region = document.getElementById('toast-region');
  }
  return region;
}

/**
 * @param {string} message
 * @param {'info'|'success'|'warning'|'danger'} [type]
 * @param {number} [duration]
 */
export function toast(message, type = 'info', duration = 3500) {
  const host = ensureRegion();
  if (!host) return;

  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.setAttribute('role', 'status');
  el.textContent = message;
  host.appendChild(el);

  requestAnimationFrame(() => el.classList.add('is-visible'));

  window.setTimeout(() => {
    el.classList.remove('is-visible');
    window.setTimeout(() => el.remove(), 280);
  }, duration);
}
