import { createIcons } from './icons.js';
import { toast } from './toast.js';
import { initMoreMenu } from './nav.js';
import { initLoginForm } from './login.js';
import { initConfirmForms } from './confirm.js';
import { initNotificationPopups } from './notifications.js';
import { initSoundToggle, playUiSound } from './sounds.js';
import { registerServiceWorker } from './pwa.js';

function bootFlashes() {
  const node = document.getElementById('boot-flashes');
  if (!node) return;
  try {
    const flashes = JSON.parse(node.textContent || '{}');
    if (flashes.error) toast(flashes.error, 'danger');
    if (flashes.success) toast(flashes.success, 'success');
    if (flashes.info) toast(flashes.info, 'info');
    if (flashes.success && flashes.sound) {
      playUiSound(String(flashes.sound));
    }
  } catch {
    // ignore
  }
}

document.addEventListener('DOMContentLoaded', () => {
  createIcons();
  initMoreMenu();
  initLoginForm();
  initConfirmForms();
  initSoundToggle();
  bootFlashes();
  initNotificationPopups();
  registerServiceWorker();
});

window.PawBerry = window.PawBerry || {};
window.PawBerry.ui = {
  toast,
  createIcons,
  playUiSound,
};
