import { createIcons } from './icons.js';

function initEventTypeForm() {
  const root = document.querySelector('[data-etype-form]');
  if (!root) return;

  const form = root.querySelector('[data-etype-form-el]');
  const nameInput = root.querySelector('[data-etype-name]');
  const previewName = root.querySelector('[data-preview-name]');
  const previewIcon = root.querySelector('[data-preview-icon]');
  const iconInput = root.querySelector('[data-etype-icon]');
  const colorInput = root.querySelector('[data-etype-color]');
  const colorCustom = root.querySelector('[data-color-custom]');
  const recurrenceToggle = root.querySelector('[data-recurrence-toggle]');
  const recurrencePanel = root.querySelector('[data-recurrence-panel]');
  const fieldList = root.querySelector('[data-field-list]');
  const fieldTemplate = root.querySelector('[data-field-template]');
  const fieldsEmpty = root.querySelector('[data-fields-empty]');
  const addFieldBtn = root.querySelector('[data-add-field]');

  const refreshPreview = () => {
    if (previewName && nameInput) {
      previewName.textContent = nameInput.value.trim() || 'Event type';
    }
    if (previewIcon && iconInput) {
      previewIcon.style.setProperty('--etype-color', colorInput?.value || '#E4BB93');
      previewIcon.innerHTML = `<i data-lucide="${iconInput.value}" aria-hidden="true"></i>`;
      createIcons(previewIcon);
    }
  };

  nameInput?.addEventListener('input', refreshPreview);

  root.querySelectorAll('[data-icon-picker] [data-icon-id]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const id = btn.getAttribute('data-icon-id');
      if (!id || !iconInput) return;
      iconInput.value = id;
      root.querySelectorAll('[data-icon-picker] [data-icon-id]').forEach((el) => {
        const on = el === btn;
        el.classList.toggle('is-selected', on);
        el.setAttribute('aria-selected', on ? 'true' : 'false');
      });
      refreshPreview();
    });
  });

  const selectColor = (hex, { syncPicker = true } = {}) => {
    if (!colorInput) return;
    const normalized = hex.toUpperCase();
    colorInput.value = normalized;
    root.querySelectorAll('[data-color-picker] [data-color]').forEach((el) => {
      const on = el.getAttribute('data-color')?.toUpperCase() === normalized;
      el.classList.toggle('is-selected', on);
      el.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (syncPicker && colorCustom && /^#[0-9A-F]{6}$/.test(normalized)) {
      colorCustom.value = normalized.toLowerCase();
    }
    refreshPreview();
  };

  root.querySelectorAll('[data-color-picker] [data-color]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const hex = btn.getAttribute('data-color');
      if (hex) selectColor(hex);
    });
  });

  colorCustom?.addEventListener('input', () => {
    const raw = (colorCustom.value || '').trim();
    if (/^#[0-9A-Fa-f]{6}$/.test(raw)) {
      selectColor(raw, { syncPicker: false });
    }
  });

  const setRecurrenceEnabled = (enabled) => {
    if (recurrencePanel) {
      recurrencePanel.hidden = !enabled;
    }
    recurrencePanel?.querySelectorAll('input, select').forEach((el) => {
      el.disabled = !enabled;
    });
  };

  recurrenceToggle?.addEventListener('change', () => {
    setRecurrenceEnabled(recurrenceToggle.checked);
  });
  setRecurrenceEnabled(!!recurrenceToggle?.checked);

  const reindexFields = () => {
    if (!fieldList) return;
    const rows = [...fieldList.querySelectorAll('[data-field-row]')];
    rows.forEach((row, index) => {
      row.querySelectorAll('[name^="fields["]').forEach((input) => {
        const name = input.getAttribute('name') || '';
        input.setAttribute('name', name.replace(/fields\[(?:\d+|__i__)\]/, `fields[${index}]`));
      });
    });
    if (fieldsEmpty) {
      fieldsEmpty.hidden = rows.length > 0;
    }
  };

  const bindFieldRow = (row) => {
    row.querySelector('.efield__handle')?.setAttribute('draggable', 'true');

    row.querySelector('[data-remove-field]')?.addEventListener('click', () => {
      row.remove();
      reindexFields();
    });

    row.querySelector('[data-move-up]')?.addEventListener('click', () => {
      const prev = row.previousElementSibling;
      if (prev?.matches('[data-field-row]')) {
        fieldList.insertBefore(row, prev);
        reindexFields();
      }
    });

    row.querySelector('[data-move-down]')?.addEventListener('click', () => {
      const next = row.nextElementSibling;
      if (next?.matches('[data-field-row]')) {
        fieldList.insertBefore(next, row);
        reindexFields();
      }
    });
  };

  fieldList?.querySelectorAll('[data-field-row]').forEach(bindFieldRow);

  addFieldBtn?.addEventListener('click', () => {
    if (!fieldTemplate || !fieldList) return;
    const node = fieldTemplate.content.firstElementChild.cloneNode(true);
    fieldList.appendChild(node);
    createIcons(node);
    bindFieldRow(node);
    reindexFields();
    node.querySelector('[data-field-name]')?.focus();
  });

  let dragRow = null;
  fieldList?.addEventListener('dragstart', (event) => {
    const handle = event.target.closest?.('.efield__handle');
    const row = event.target.closest?.('[data-field-row]');
    if (!handle || !row) {
      event.preventDefault();
      return;
    }
    dragRow = row;
    row.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });
  fieldList?.addEventListener('dragend', () => {
    dragRow?.classList.remove('is-dragging');
    dragRow = null;
    reindexFields();
  });
  fieldList?.addEventListener('dragover', (event) => {
    event.preventDefault();
    const over = event.target.closest?.('[data-field-row]');
    if (!dragRow || !over || over === dragRow) return;
    const rect = over.getBoundingClientRect();
    const before = event.clientY < rect.top + rect.height / 2;
    fieldList.insertBefore(dragRow, before ? over : over.nextSibling);
  });

  form?.addEventListener('submit', () => {
    const submit = form.querySelector('[data-submit]');
    const label = form.querySelector('[data-submit-label]');
    const loading = form.querySelector('[data-submit-loading]');
    if (submit) submit.classList.add('is-loading');
    if (label) label.hidden = true;
    if (loading) loading.hidden = false;
  });

  refreshPreview();
  reindexFields();
}

document.addEventListener('DOMContentLoaded', initEventTypeForm);
