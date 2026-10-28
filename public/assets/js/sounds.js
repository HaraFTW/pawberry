const STORAGE_KEY = 'pawberry.soundsEnabled';

let audioCtx = null;

function readEnabled() {
  try {
    return localStorage.getItem(STORAGE_KEY) === '1';
  } catch {
    return false;
  }
}

function writeEnabled(enabled) {
  try {
    localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
  } catch {
    // private mode
  }
}

function getContext() {
  if (audioCtx) return audioCtx;
  const Ctx = window.AudioContext || window.webkitAudioContext;
  if (!Ctx) return null;
  audioCtx = new Ctx();
  return audioCtx;
}

function tone(ctx, { frequency, start, duration, gain = 0.035, type = 'sine' }) {
  const osc = ctx.createOscillator();
  const amp = ctx.createGain();
  osc.type = type;
  osc.frequency.value = frequency;
  amp.gain.setValueAtTime(0.0001, start);
  amp.gain.exponentialRampToValueAtTime(gain, start + 0.02);
  amp.gain.exponentialRampToValueAtTime(0.0001, start + duration);
  osc.connect(amp);
  amp.connect(ctx.destination);
  osc.start(start);
  osc.stop(start + duration + 0.02);
}

/**
 * Soft local Web Audio chimes — no external assets.
 * @param {'success'|'complete'|'member'|'type'} kind
 */
export function playUiSound(kind = 'success') {
  if (!readEnabled()) return;
  const ctx = getContext();
  if (!ctx) return;

  const run = () => {
    const t0 = ctx.currentTime + 0.01;
    if (kind === 'complete') {
      tone(ctx, { frequency: 523.25, start: t0, duration: 0.12, gain: 0.03 });
      tone(ctx, { frequency: 659.25, start: t0 + 0.1, duration: 0.14, gain: 0.028 });
      tone(ctx, { frequency: 783.99, start: t0 + 0.2, duration: 0.18, gain: 0.025 });
      return;
    }
    if (kind === 'member') {
      tone(ctx, { frequency: 392.0, start: t0, duration: 0.14, gain: 0.03, type: 'triangle' });
      tone(ctx, { frequency: 523.25, start: t0 + 0.12, duration: 0.16, gain: 0.026, type: 'triangle' });
      return;
    }
    if (kind === 'type') {
      tone(ctx, { frequency: 440.0, start: t0, duration: 0.1, gain: 0.025 });
      tone(ctx, { frequency: 554.37, start: t0 + 0.09, duration: 0.12, gain: 0.022 });
      return;
    }
    // success default
    tone(ctx, { frequency: 587.33, start: t0, duration: 0.11, gain: 0.03 });
    tone(ctx, { frequency: 739.99, start: t0 + 0.1, duration: 0.16, gain: 0.026 });
  };

  if (ctx.state === 'suspended') {
    ctx.resume().then(run).catch(() => {});
    return;
  }
  run();
}

export function areSoundsEnabled() {
  return readEnabled();
}

export function setSoundsEnabled(enabled) {
  writeEnabled(!!enabled);
}

export function initSoundToggle() {
  document.querySelectorAll('[data-sound-toggle]').forEach((btn) => {
    const sync = () => {
      const on = readEnabled();
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      btn.setAttribute('aria-checked', on ? 'true' : 'false');
      btn.classList.toggle('is-on', on);
      const label = btn.querySelector('[data-sound-label]');
      if (label) label.textContent = on ? 'Sounds on' : 'Sounds off';
    };

    sync();
    btn.addEventListener('click', (event) => {
      event.preventDefault();
      const next = !readEnabled();
      writeEnabled(next);
      sync();
      if (next) {
        // Confirm enable with a soft chime (user-initiated).
        playUiSound('type');
      }
    });
  });
}
