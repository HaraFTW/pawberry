<?php

use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $active */
/** @var string $variant desktop|mobile|more */
/** @var int $notificationAttentionCount */

$items = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => '/', 'icon' => 'layout-dashboard', 'primary' => true],
    ['id' => 'gang', 'label' => 'The Gang', 'href' => '/gang', 'icon' => 'paw-print', 'primary' => true],
    ['id' => 'events', 'label' => 'Events', 'href' => '/events', 'icon' => 'notebook-pen', 'primary' => true],
    ['id' => 'calendar', 'label' => 'Calendar', 'href' => '/calendar', 'icon' => 'calendar-days', 'primary' => true],
    ['id' => 'event-types', 'label' => 'Event Types', 'href' => '/event-types', 'icon' => 'tags', 'primary' => false],
    ['id' => 'notifications', 'label' => 'Notifications', 'href' => '/notifications', 'icon' => 'bell', 'primary' => false],
    ['id' => 'settings', 'label' => 'Settings', 'href' => '/settings', 'icon' => 'settings', 'primary' => false],
];

$variant = $variant ?? 'desktop';
$badgeCount = (int) ($notificationAttentionCount ?? 0);

foreach ($items as $item) {
    if ($variant === 'mobile' && !$item['primary']) {
        continue;
    }
    if ($variant === 'more' && ($item['primary'] || $item['id'] === 'notifications')) {
        continue;
    }

    $isActive = ($active ?? '') === $item['id'];
    $classes = 'nav-link';
    if ($isActive) {
        $classes .= ' is-active';
    }
    if ($variant === 'mobile') {
        $classes .= ' nav-link--mobile';
    }
    $showBadge = $item['id'] === 'notifications' && $badgeCount > 0;
    ?>
    <a class="<?= $classes ?>" href="<?= View::e(Url::to($item['href'])) ?>" <?= $isActive ? 'aria-current="page"' : '' ?>>
        <span class="nav-link__icon-wrap">
            <i data-lucide="<?= View::e($item['icon']) ?>" aria-hidden="true"></i>
            <?php if ($showBadge): ?>
                <span class="nav-badge" aria-label="<?= $badgeCount ?> reminders need attention"><?= $badgeCount > 9 ? '9+' : $badgeCount ?></span>
            <?php endif; ?>
        </span>
        <span><?= View::e($item['label']) ?></span>
    </a>
    <?php
}
