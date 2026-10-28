<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $active */
$moreActive = in_array($active ?? '', ['event-types', 'settings'], true);
?>
<nav class="bottom-nav" aria-label="Mobile">
    <?php $variant = 'mobile'; require __DIR__ . '/nav-items.php'; ?>

    <div class="more-menu" data-more-menu>
        <button
            type="button"
            class="nav-link nav-link--mobile more-menu__toggle<?= $moreActive ? ' is-active' : '' ?>"
            aria-expanded="false"
            aria-controls="more-panel"
            data-more-toggle
        >
            <span class="nav-link__icon-wrap">
                <i data-lucide="ellipsis" aria-hidden="true"></i>
            </span>
            <span>More</span>
        </button>

        <div id="more-panel" class="more-menu__panel" hidden data-more-panel>
            <div class="more-menu__sheet" role="menu">
                <?php $variant = 'more'; require __DIR__ . '/nav-items.php'; ?>
                <button
                    type="button"
                    class="nav-link"
                    role="menuitemcheckbox"
                    aria-checked="false"
                    aria-pressed="false"
                    data-sound-toggle
                >
                    <i data-lucide="volume-2" aria-hidden="true"></i>
                    <span data-sound-label>Sounds off</span>
                </button>
                <form method="post" action="<?= View::e(Url::to('/logout')) ?>" role="none">
                    <?= Csrf::field() ?>
                    <button type="submit" class="nav-link" role="menuitem">
                        <i data-lucide="log-out" aria-hidden="true"></i>
                        <span>Sign out</span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
