import { createIcons } from './icons.js';

function initEventsIndex() {
  const root = document.querySelector('[data-events-index]');
  if (!root) return;

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

  // Active filters start open; inactive stay closed. User can still toggle either way.
  setExpanded(root.getAttribute('data-filters-active') === '1');

  root.querySelectorAll('[data-member-filter-check]').forEach((box) => {
    const sync = () => {
      box.closest('.member-filter__option')?.classList.toggle('is-selected', box.checked);
    };
    box.addEventListener('change', sync);
    sync();
  });

  createIcons(root);
}

document.addEventListener('DOMContentLoaded', initEventsIndex);
