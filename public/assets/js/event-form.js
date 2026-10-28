import { createIcons } from './icons.js';

function initEventForm() {
  const root = document.querySelector('[data-event-form]');
  if (!root) return;

  const form = root.querySelector('[data-event-form-el]');
  const fieldsHost = root.querySelector('[data-custom-fields]');
  const fieldsHint = root.querySelector('[data-custom-fields-hint]');
  const typeConfirm = root.querySelector('[data-type-change-confirm]');
  const originalType = Number(root.getAttribute('data-original-type') || '0');
  const historicalHost = root.querySelector('[data-historical-fields]');

  let config = { fieldsByType: {}, oldFields: {}, existingValues: [], errors: {} };
  try {
    const raw = document.getElementById('event-fields-data');
    if (raw?.textContent) {
      config = JSON.parse(raw.textContent);
    }
  } catch {
    // ignore
  }

  const valueForField = (field) => {
    const key = field.field_key;
    if (Object.prototype.hasOwnProperty.call(config.oldFields, key)) {
      const v = config.oldFields[key];
      return Array.isArray(v) ? '' : String(v ?? '');
    }
    const existing = (config.existingValues || []).find(
      (row) => Number(row.id) === Number(field.id) && !row.deleted
    );
    return existing ? String(existing.value ?? '') : '';
  };

  const fieldError = (key) => config.errors?.[`fields.${key}`] || '';

  const renderFields = (typeId) => {
    if (!fieldsHost) return;
    const fields = config.fieldsByType?.[String(typeId)] || [];
    if (fieldsHint) {
      fieldsHint.hidden = fields.length > 0;
      fieldsHint.textContent = fields.length
        ? ''
        : typeId
          ? 'This event type has no custom fields.'
          : 'Choose an event type to see any extra fields.';
    }

    if (fields.length === 0) {
      fieldsHost.innerHTML = '';
      return;
    }

    fieldsHost.innerHTML = fields
      .map((field) => {
        const val = valueForField(field);
        const err = fieldError(field.field_key);
        const req = field.is_required ? ' required' : '';
        const reqLabel = field.is_required
          ? ''
          : ' <span class="field__optional">optional</span>';
        const invalid = err ? ' is-invalid' : '';
        let control = '';

        if (field.field_type === 'checkbox') {
          control = `
            <label class="check">
              <input type="hidden" name="fields[${field.field_key}]" value="0">
              <input type="checkbox" name="fields[${field.field_key}]" value="1" ${val === '1' ? 'checked' : ''}>
              <span>Yes</span>
            </label>`;
        } else if (field.field_type === 'rating') {
          const opts = [1, 2, 3, 4, 5]
            .map((n) => `<option value="${n}" ${val === String(n) ? 'selected' : ''}>${n}</option>`)
            .join('');
          control = `<select class="input${invalid}" name="fields[${field.field_key}]"${req}>
            <option value="">—</option>${opts}</select>`;
        } else if (field.field_type === 'date') {
          control = `<input class="input${invalid}" type="date" name="fields[${field.field_key}]" value="${escapeAttr(val)}"${req}>`;
        } else if (field.field_type === 'number') {
          control = `<input class="input${invalid}" type="number" step="any" name="fields[${field.field_key}]" value="${escapeAttr(val)}"${req}>`;
        } else {
          control = `<input class="input${invalid}" type="text" name="fields[${field.field_key}]" maxlength="2000" value="${escapeAttr(val)}"${req}>`;
        }

        return `<div class="field">
          <label class="field__label">${escapeHtml(field.name)}${reqLabel}</label>
          ${control}
          <p class="field__error" ${err ? '' : 'hidden'}>${escapeHtml(err)}</p>
        </div>`;
      })
      .join('');
  };

  const selectedTypeId = () => {
    const checked = root.querySelector('[data-type-radio]:checked');
    return checked ? Number(checked.value) : 0;
  };

  const syncTypeUI = () => {
    const typeId = selectedTypeId();
    root.querySelectorAll('.event-type-option').forEach((el) => {
      const input = el.querySelector('[data-type-radio]');
      el.classList.toggle('is-selected', !!(input && input.checked));
    });

    if (typeConfirm) {
      const changed = originalType > 0 && typeId > 0 && typeId !== originalType;
      typeConfirm.hidden = !changed;
      if (!changed) {
        const box = typeConfirm.querySelector('input[type="checkbox"]');
        if (box) box.checked = false;
      }
      if (historicalHost) {
        historicalHost.hidden = changed;
      }
    }

    renderFields(typeId);
  };

  root.querySelectorAll('[data-type-radio]').forEach((radio) => {
    radio.addEventListener('change', syncTypeUI);
  });

  root.querySelectorAll('[data-member-check]').forEach((box) => {
    const sync = () => {
      box.closest('.member-picker__option')?.classList.toggle('is-selected', box.checked);
    };
    box.addEventListener('change', sync);
    sync();
  });

  const fileInput = root.querySelector('[data-attachments-input]');
  const fileSummary = root.querySelector('[data-attachments-summary]');
  fileInput?.addEventListener('change', () => {
    const files = [...(fileInput.files || [])];
    if (!fileSummary) return;
    if (files.length === 0) {
      fileSummary.hidden = true;
      fileSummary.textContent = '';
      return;
    }
    fileSummary.hidden = false;
    fileSummary.textContent = files.map((f) => f.name).join(', ');
  });

  form?.addEventListener('submit', () => {
    const submit = form.querySelector('[data-submit]');
    const label = form.querySelector('[data-submit-label]');
    const loading = form.querySelector('[data-submit-loading]');
    if (submit) submit.classList.add('is-loading');
    if (label) label.hidden = true;
    if (loading) loading.hidden = false;
  });

  syncTypeUI();
  createIcons(root);
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function escapeAttr(value) {
  return escapeHtml(value).replace(/'/g, '&#39;');
}

document.addEventListener('DOMContentLoaded', initEventForm);
