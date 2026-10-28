<?php

use PawBerry\Core\Config;
use PawBerry\Core\View;

/** @var array<string, mixed>|null $user */
?>
<section class="page">
    <header class="page__header">
        <h1 class="page__title">Settings</h1>
        <p class="page__lede">Quiet preferences for your private journal.</p>
    </header>

    <div class="settings-panel">
        <h2 class="settings-panel__title">Account</h2>
        <dl class="settings-list">
            <div>
                <dt>Keeper</dt>
                <dd><?= View::e((string) ($user['display_name'] ?? 'Keeper')) ?></dd>
            </div>
            <div>
                <dt>Environment</dt>
                <dd><?= View::e(Config::env()) ?></dd>
            </div>
            <div>
                <dt>Base path</dt>
                <dd><code><?= View::e(Config::basePath() === '' ? '/' : Config::basePath()) ?></code></dd>
            </div>
        </dl>
        <p class="settings-panel__note">Passphrase changes and richer preferences arrive in a later phase.</p>
    </div>

    <div class="settings-panel settings-panel--spaced">
        <h2 class="settings-panel__title">Sounds</h2>
        <p class="settings-panel__note" style="margin-top:0">Optional soft confirmation chimes after successful saves. Off by default.</p>
        <button
            type="button"
            class="btn btn--ghost sound-settings-toggle"
            aria-pressed="false"
            data-sound-toggle
        >
            <i data-lucide="volume-2" aria-hidden="true"></i>
            <span data-sound-label>Sounds off</span>
        </button>
    </div>
</section>
