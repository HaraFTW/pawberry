<?php

use PawBerry\Core\Config;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $title */
/** @var string $active */
/** @var int $notificationAttentionCount */

$attentionCount = (int) ($notificationAttentionCount ?? 0);
$notifActive = ($active ?? '') === 'notifications';
$notifLabel = $attentionCount > 0
    ? 'Notifications, ' . $attentionCount . ' need attention'
    : 'Notifications';
?>
<header class="topbar">
    <div class="topbar__brand">
        <span class="topbar__mark" aria-hidden="true"></span>
        <span class="topbar__title"><?= View::e($title ?? Config::appName()) ?></span>
    </div>

    <a
        class="topbar__notifications<?= $notifActive ? ' is-active' : '' ?>"
        href="<?= View::e(Url::to('/notifications')) ?>"
        aria-label="<?= View::e($notifLabel) ?>"
        <?= $notifActive ? 'aria-current="page"' : '' ?>
    >
        <span class="topbar__notifications-icon">
            <i data-lucide="bell" aria-hidden="true"></i>
            <?php if ($attentionCount > 0): ?>
                <span class="nav-badge" aria-hidden="true"><?= $attentionCount > 9 ? '9+' : $attentionCount ?></span>
            <?php endif; ?>
        </span>
    </a>
</header>
