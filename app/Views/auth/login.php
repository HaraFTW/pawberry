<?php

use PawBerry\Core\Config;
use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string|null $error */
/** @var bool $oldRemember */
?>
<section class="login">
    <div class="login__glow" aria-hidden="true"></div>

    <div class="login__card">
        <div class="login__brand">
            <img class="login__paw" src="<?= View::e(Url::asset('icons/icon-192.png')) ?>" alt="" width="64" height="64">
            <h1 class="login__name"><?= View::e(Config::appName()) ?></h1>
            <p class="login__welcome">Welcome home. The Gang is waiting.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert--danger" role="alert">
                <?= View::e((string) $error) ?>
            </div>
        <?php endif; ?>

        <form class="login__form" method="post" action="<?= View::e(Url::to('/login')) ?>" data-login-form novalidate>
            <?= Csrf::field() ?>

            <div class="field">
                <label class="field__label" for="passphrase">Passphrase</label>
                <div class="field__control field__control--password">
                    <input
                        class="input"
                        type="password"
                        id="passphrase"
                        name="passphrase"
                        autocomplete="current-password"
                        required
                        autofocus
                        aria-describedby="passphrase-hint"
                    >
                    <button type="button" class="icon-btn" data-toggle-password aria-label="Show passphrase" aria-pressed="false">
                        <i data-lucide="eye" data-icon-show aria-hidden="true"></i>
                        <i data-lucide="eye-off" data-icon-hide aria-hidden="true" hidden></i>
                    </button>
                </div>
                <p id="passphrase-hint" class="field__hint">Your private key to this cozy journal.</p>
            </div>

            <label class="check">
                <input type="checkbox" name="remember" value="1" <?= !empty($oldRemember) ? 'checked' : '' ?>>
                <span>Remember me for a year</span>
            </label>

            <button type="submit" class="btn btn--primary btn--block" data-submit>
                <span data-submit-label>Enter PawBerry</span>
                <span data-submit-loading hidden>Opening the door…</span>
            </button>
        </form>
    </div>
</section>
