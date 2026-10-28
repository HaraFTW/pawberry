<?php

use PawBerry\Core\Config;
use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $active */
?>
<aside class="sidebar" aria-label="Primary">
    <div class="sidebar__brand">
        <img src="<?= View::e(Url::asset('icons/paw.svg')) ?>" alt="" width="36" height="36">
        <div>
            <p class="sidebar__name"><?= View::e(Config::appName()) ?></p>
            <p class="sidebar__tag">The Gang's journal</p>
        </div>
    </div>

    <nav class="sidebar__nav">
        <?php $variant = 'desktop'; require __DIR__ . '/nav-items.php'; ?>
    </nav>

    <form class="sidebar__logout" method="post" action="<?= View::e(Url::to('/logout')) ?>">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn--ghost btn--block">
            <i data-lucide="log-out" aria-hidden="true"></i>
            <span>Sign out</span>
        </button>
    </form>
</aside>
