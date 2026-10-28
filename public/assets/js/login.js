export function initLoginForm() {
  const form = document.querySelector('[data-login-form]');
  if (!form) return;

  const toggle = form.querySelector('[data-toggle-password]');
  const input = form.querySelector('#passphrase');
  const submit = form.querySelector('[data-submit]');
  const label = form.querySelector('[data-submit-label]');
  const loading = form.querySelector('[data-submit-loading]');

  if (toggle && input) {
    const iconShow = toggle.querySelector('[data-icon-show]');
    const iconHide = toggle.querySelector('[data-icon-hide]');
    toggle.addEventListener('click', () => {
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
      toggle.setAttribute('aria-label', showing ? 'Show passphrase' : 'Hide passphrase');
      if (iconShow) iconShow.hidden = !showing;
      if (iconHide) iconHide.hidden = showing;
    });
  }

  form.addEventListener('submit', () => {
    if (!submit) return;
    submit.disabled = true;
    submit.classList.add('is-loading');
    if (label) label.hidden = true;
    if (loading) loading.hidden = false;
  });
}
