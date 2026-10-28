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

    const setIconVisibility = (passwordVisible) => {
      // SVG elements have no `hidden` IDL property — toggle the attribute instead.
      if (iconShow) {
        if (passwordVisible) iconShow.setAttribute('hidden', '');
        else iconShow.removeAttribute('hidden');
      }
      if (iconHide) {
        if (passwordVisible) iconHide.removeAttribute('hidden');
        else iconHide.setAttribute('hidden', '');
      }
    };

    setIconVisibility(input.type === 'text');

    toggle.addEventListener('click', () => {
      const passwordVisible = input.type !== 'text';
      input.type = passwordVisible ? 'text' : 'password';
      toggle.setAttribute('aria-pressed', passwordVisible ? 'true' : 'false');
      toggle.setAttribute(
        'aria-label',
        passwordVisible ? 'Hide passphrase' : 'Show passphrase'
      );
      setIconVisibility(passwordVisible);
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
