import { createIcons } from './icons.js';
import { toast } from './toast.js';

function snoozePopupUrl(id) {
  const template = window.PawBerry?.urls?.snoozePopup;
  if (typeof template === 'string' && template.includes('__ID__')) {
    return template.replace('__ID__', String(id));
  }
  const base = window.PawBerry?.urls?.notifications || '/notifications';
  return `${base}/${id}/snooze-popup`;
}

async function postSnooze(url) {
  const token = window.PawBerry?.csrfToken || '';
  const response = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': token,
      'X-Requested-With': 'XMLHttpRequest',
    },
    body: JSON.stringify({ _csrf: token }),
  });

  let data = null;
  try {
    data = await response.json();
  } catch {
    data = null;
  }

  if (!response.ok || !data?.ok) {
    const message = data?.error || 'Could not snooze that reminder. Please try again.';
    const error = new Error(message);
    error.status = response.status;
    throw error;
  }

  return data;
}

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function removeCard(card) {
  if (!card || !card.isConnected) return;
  if (prefersReducedMotion()) {
    card.remove();
    syncSnoozeAllButton();
    return;
  }
  card.classList.add('is-leaving');
  window.setTimeout(() => {
    card.remove();
    syncSnoozeAllButton();
  }, 180);
}

function syncSnoozeAllButton() {
  const region = document.getElementById('notif-popup-region');
  if (!region) return;
  const button = region.querySelector('[data-snooze-all]');
  const hasPopups = region.querySelectorAll('.notif-popup').length > 0;
  if (!hasPopups) {
    button?.remove();
    return;
  }
  if (!button) {
    region.appendChild(buildSnoozeAllButton());
  }
}

function buildSnoozeAllButton() {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'notif-popup-snooze-all';
  button.dataset.snoozeAll = '1';
  button.textContent = 'Snooze all';
  button.addEventListener('click', async () => {
    if (button.disabled) return;
    button.disabled = true;
    try {
      const url = window.PawBerry?.urls?.snoozeAllPopups
        || `${window.PawBerry?.urls?.notifications || '/notifications'}/snooze-popups`;
      await postSnooze(url);
      const region = document.getElementById('notif-popup-region');
      region?.querySelectorAll('.notif-popup').forEach((card) => removeCard(card));
      button.remove();
    } catch (error) {
      button.disabled = false;
      toast(error.message || 'Could not snooze reminders. Please try again.', 'danger');
    }
  });
  return button;
}

function buildCard(item) {
  const card = document.createElement('article');
  const urgency = item.urgency ?? null;
  card.className = urgency
    ? `notif-popup notif-popup--urgency-${urgency}`
    : `notif-popup notif-popup--${item.state || 'due_soon'}`;
  card.setAttribute('role', 'status');
  card.dataset.notificationId = String(item.id);
  if (urgency) {
    card.dataset.urgency = urgency;
  }

  const iconWrap = document.createElement('div');
  iconWrap.className = 'notif-popup__icon';
  iconWrap.style.setProperty('--etype-color', item.color || '#E4BB93');
  iconWrap.setAttribute('aria-hidden', 'true');
  const icon = document.createElement('i');
  icon.dataset.lucide = item.icon || 'bell';
  iconWrap.appendChild(icon);

  const body = document.createElement('div');
  body.className = 'notif-popup__body';

  const message = document.createElement('p');
  message.className = 'notif-popup__message';
  message.textContent = item.message || '';

  const actions = document.createElement('div');
  actions.className = 'notif-popup__actions';

  const open = document.createElement('a');
  open.className = 'notif-popup__link';
  open.href = item.href || (window.PawBerry?.urls?.notifications ?? '/notifications');
  open.textContent = 'View';

  actions.appendChild(open);
  body.appendChild(message);
  body.appendChild(actions);

  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'notif-popup__close';
  close.setAttribute('aria-label', 'Snooze until tomorrow');
  close.innerHTML = '<i data-lucide="x" aria-hidden="true"></i>';
  close.addEventListener('click', async () => {
    if (close.disabled) return;
    close.disabled = true;
    try {
      await postSnooze(snoozePopupUrl(item.id));
      removeCard(card);
    } catch (error) {
      close.disabled = false;
      toast(error.message || 'Could not snooze that reminder. Please try again.', 'danger');
    }
  });

  card.appendChild(iconWrap);
  card.appendChild(body);
  card.appendChild(close);
  return card;
}

export function initNotificationPopups() {
  const node = document.getElementById('boot-notification-popups');
  const region = document.getElementById('notif-popup-region');
  if (!node || !region) return;

  let items = [];
  try {
    items = JSON.parse(node.textContent || '[]');
  } catch {
    return;
  }
  if (!Array.isArray(items) || items.length === 0) return;

  const seen = new Set();
  const visible = [];
  for (const item of items) {
    if (!item || item.id == null) continue;
    const id = String(item.id);
    if (seen.has(id)) continue;
    seen.add(id);
    visible.push(item);
  }
  if (visible.length === 0) return;

  // Avoid overwhelming: show at most 3 stacked popups.
  visible.slice(0, 3).forEach((item, index) => {
    const card = buildCard(item);
    card.style.setProperty('--popup-index', String(index));
    region.appendChild(card);
  });

  region.appendChild(buildSnoozeAllButton());
  createIcons(region);
}
