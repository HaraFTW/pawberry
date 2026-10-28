import { createIcons } from './icons.js';

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function formatLongDate(iso) {
  const dt = new Date(`${iso}T12:00:00`);
  if (Number.isNaN(dt.getTime())) return iso;
  return dt.toLocaleDateString(undefined, {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function prefersReducedMotion() {
  return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches === true;
}

function isMobileViewport() {
  return window.matchMedia?.('(max-width: 720px)')?.matches === true;
}

function daysInMonth(year, month) {
  return new Date(year, month, 0).getDate();
}

function renderDetails(body, day, urls) {
  const events = day?.events ?? [];
  const notifications = day?.notifications ?? [];

  if (events.length === 0 && notifications.length === 0) {
    body.innerHTML = `<div class="cal-details__empty" data-cal-empty><p>Nothing recorded or due on this day.</p></div>`;
    return;
  }

  let html = '';
  if (events.length > 0) {
    html += `<h3 class="cal-details__section-title">Recorded events</h3><ul class="cal-details__list">`;
    for (const event of events) {
      html += `
        <li class="cal-item cal-item--event">
          <div class="cal-item__icon" style="--etype-color: ${escapeHtml(event.type_color)}" aria-hidden="true">
            <i data-lucide="${escapeHtml(event.type_icon)}"></i>
          </div>
          <div class="cal-item__body">
            <a class="cal-item__title" href="${escapeHtml(urls.eventBase)}/${encodeURIComponent(event.id)}">
              ${escapeHtml(event.title)}
            </a>
            <p class="cal-item__meta">
              ${escapeHtml(event.type_name)}
              <span aria-hidden="true"> · </span>
              ${escapeHtml(event.member_names)}
            </p>
          </div>
          <span class="cal-item__badge">Recorded</span>
        </li>`;
    }
    html += `</ul>`;
  }

  if (notifications.length > 0) {
    html += `<h3 class="cal-details__section-title">Due dates</h3><ul class="cal-details__list">`;
    for (const n of notifications) {
      html += `
        <li class="cal-item cal-item--due cal-item--${escapeHtml(n.status)}">
          <div class="cal-item__icon cal-item__icon--outline" style="--etype-color: ${escapeHtml(n.type_color)}" aria-hidden="true">
            <i data-lucide="${escapeHtml(n.type_icon)}"></i>
          </div>
          <div class="cal-item__body">
            <a class="cal-item__title" href="${escapeHtml(urls.notifications)}">
              ${escapeHtml(n.activity)}
            </a>
            <p class="cal-item__meta">
              ${escapeHtml(n.type_name)}
              <span aria-hidden="true"> · </span>
              ${escapeHtml(n.member_names)}
            </p>
          </div>
          <span class="cal-item__badge">${escapeHtml(n.status_label)}</span>
        </li>`;
    }
    html += `</ul>`;
  }

  body.innerHTML = html;
  createIcons(body);
}

function updateUrl(date) {
  try {
    const url = new URL(window.location.href);
    url.searchParams.set('day', date);
    window.history.replaceState({}, '', url.toString());
  } catch {
    // ignore
  }
}

function syncAddEventButton(root, date, today, urls) {
  const btn = root.querySelector('[data-cal-add-event]');
  if (!btn) return;
  const canPrefill = date <= today;
  const prefill = canPrefill ? date : today;
  btn.setAttribute('href', `${urls.addEventBase}?date=${encodeURIComponent(prefill)}`);
  btn.classList.add('btn--primary');
  btn.classList.remove('btn--ghost');
  if (canPrefill) {
    btn.removeAttribute('data-cal-add-future');
  } else {
    btn.setAttribute('data-cal-add-future', '');
  }
}

function scrollDetailsIntoView(root) {
  if (!isMobileViewport()) return;
  const details = root.querySelector('[data-cal-details]');
  if (!details) return;
  details.scrollIntoView({
    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    block: 'start',
  });
}

function selectDay(root, date, boot, options = {}) {
  const days = boot.days || {};
  const today = boot.today;
  const urls = boot.urls || {};

  root.querySelectorAll('[data-cal-day]').forEach((btn) => {
    const isSelected = btn.getAttribute('data-cal-day') === date;
    btn.classList.toggle('is-selected', isSelected);
    btn.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
  });

  const title = root.querySelector('[data-cal-details-title]');
  if (title) title.textContent = formatLongDate(date);

  const body = root.querySelector('[data-cal-details-body]');
  if (body) renderDetails(body, days[date], urls);

  syncAddEventButton(root, date, today, urls);
  root.setAttribute('data-selected', date);
  updateUrl(date);

  const dayInput = root.querySelector('#calendar-filters-panel input[name="day"]');
  if (dayInput) dayInput.value = date;

  if (options.scrollToDetails) {
    scrollDetailsIntoView(root);
  }
}

function buildMonthNavigateUrl(year, month, selectedDay) {
  const url = new URL(window.location.href);
  url.searchParams.set('year', String(year));
  url.searchParams.set('month', String(month));
  const dom = Math.max(1, parseInt(String(selectedDay || '').slice(8, 10), 10) || 1);
  const clamped = Math.min(dom, daysInMonth(year, month));
  url.searchParams.set('day', `${year}-${String(month).padStart(2, '0')}-${String(clamped).padStart(2, '0')}`);
  return url.toString();
}

function initMonthPicker(root, boot) {
  const wrap = root.querySelector('[data-cal-month-picker]');
  const toggle = root.querySelector('[data-cal-month-toggle]');
  const panel = root.querySelector('[data-cal-month-panel]');
  const yearLabel = root.querySelector('[data-cal-year-label]');
  if (!wrap || !toggle || !panel || !yearLabel) return;

  let viewYear = Number(boot.year) || Number(root.getAttribute('data-year')) || new Date().getFullYear();
  const currentMonth = Number(boot.month) || Number(root.getAttribute('data-month')) || 1;

  const syncMonthHighlight = () => {
    panel.querySelectorAll('[data-cal-pick-month]').forEach((btn) => {
      const month = Number(btn.getAttribute('data-cal-pick-month'));
      const isCurrent = viewYear === Number(boot.year) && month === currentMonth;
      btn.classList.toggle('is-current', isCurrent);
      btn.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
    });
  };

  const setOpen = (open) => {
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    toggle.classList.toggle('is-open', open);
    wrap.classList.toggle('is-open', open);
    panel.hidden = !open;
    if (open) {
      viewYear = Number(boot.year) || viewYear;
      yearLabel.textContent = String(viewYear);
      syncMonthHighlight();
      const currentBtn = panel.querySelector('.cal-month-picker__month.is-current')
        || panel.querySelector('[data-cal-pick-month]');
      currentBtn?.focus();
    }
  };

  const close = () => setOpen(false);

  toggle.addEventListener('click', (event) => {
    event.stopPropagation();
    const open = toggle.getAttribute('aria-expanded') === 'true';
    setOpen(!open);
  });

  root.querySelector('[data-cal-year-prev]')?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    viewYear -= 1;
    if (viewYear < 1970) viewYear = 1970;
    yearLabel.textContent = String(viewYear);
    syncMonthHighlight();
  });

  root.querySelector('[data-cal-year-next]')?.addEventListener('click', (event) => {
    event.preventDefault();
    event.stopPropagation();
    viewYear += 1;
    if (viewYear > 2100) viewYear = 2100;
    yearLabel.textContent = String(viewYear);
    syncMonthHighlight();
  });

  panel.querySelectorAll('[data-cal-pick-month]').forEach((btn) => {
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const month = Number(btn.getAttribute('data-cal-pick-month'));
      if (!month) return;
      const selected = root.getAttribute('data-selected') || boot.selected || boot.today;
      window.location.href = buildMonthNavigateUrl(viewYear, month, selected);
    });
  });

  document.addEventListener('click', (event) => {
    if (panel.hidden) return;
    if (wrap.contains(event.target)) return;
    close();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) {
      event.preventDefault();
      close();
      toggle.focus();
    }
  });
}

function initFilters(root) {
  const toggle = root.querySelector('[data-filters-toggle]');
  const panel = root.querySelector('[data-filters-panel]');

  const setExpanded = (expanded) => {
    if (!toggle || !panel) return;
    toggle.classList.toggle('is-expanded', expanded);
    toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    panel.classList.toggle('is-open', expanded);
    panel.hidden = !expanded;
  };

  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') === 'true';
    setExpanded(!open);
  });

  setExpanded(root.getAttribute('data-filters-active') === '1');

  root.querySelectorAll('[data-member-filter-check]').forEach((box) => {
    const sync = () => {
      box.closest('.member-filter__option')?.classList.toggle('is-selected', box.checked);
    };
    box.addEventListener('change', sync);
    sync();
  });
}

function initCalendar() {
  const root = document.querySelector('[data-calendar]');
  if (!root) return;

  let boot = {
    days: {},
    today: root.getAttribute('data-today') || '',
    year: Number(root.getAttribute('data-year')) || undefined,
    month: Number(root.getAttribute('data-month')) || undefined,
    urls: {},
  };
  const bootNode = document.getElementById('calendar-boot');
  if (bootNode) {
    try {
      boot = { ...boot, ...JSON.parse(bootNode.textContent || '{}') };
    } catch {
      // keep defaults
    }
  }

  initFilters(root);
  initMonthPicker(root, boot);

  root.querySelectorAll('[data-cal-day]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const date = btn.getAttribute('data-cal-day');
      if (date) selectDay(root, date, boot, { scrollToDetails: true });
    });
  });

  root.querySelector('.cal-grid')?.addEventListener('keydown', (event) => {
    const current = event.target.closest?.('[data-cal-day]');
    if (!current) return;
    const buttons = [...root.querySelectorAll('[data-cal-day]')];
    const index = buttons.indexOf(current);
    if (index < 0) return;

    let next = null;
    if (event.key === 'ArrowRight') next = buttons[index + 1];
    if (event.key === 'ArrowLeft') next = buttons[index - 1];
    if (event.key === 'ArrowDown') next = buttons[index + 7];
    if (event.key === 'ArrowUp') next = buttons[index - 7];
    if (event.key === 'Home') next = buttons[index - (index % 7)];
    if (event.key === 'End') next = buttons[index - (index % 7) + 6];

    if (next) {
      event.preventDefault();
      next.focus();
      const date = next.getAttribute('data-cal-day');
      if (date) selectDay(root, date, boot, { scrollToDetails: true });
    }
  });

  createIcons(root);
}

document.addEventListener('DOMContentLoaded', initCalendar);
