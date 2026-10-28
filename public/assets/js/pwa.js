export function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;

  const base = window.PawBerry?.basePath || '';
  const swUrl = `${base}/sw.js`;
  const scope = base === '' ? '/' : `${base}/`;

  window.addEventListener('load', () => {
    navigator.serviceWorker.register(swUrl, { scope }).catch(() => {
      // Silent in production foundation; SW is optional enhancement.
    });
  });
}
