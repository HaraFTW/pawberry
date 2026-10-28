<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\NotificationPresenter;

/** @var string $view */
/** @var list<array<string, mixed>> $items */
/** @var int $attentionCount */
/** @var int $upcomingCount */

$view = in_array($view ?? 'active', ['active', 'upcoming', 'history'], true) ? $view : 'active';
$attentionCount = (int) ($attentionCount ?? 0);
$upcomingCount = (int) ($upcomingCount ?? 0);
$isActive = $view === 'active';
$isUpcoming = $view === 'upcoming';
$isHistory = $view === 'history';

$viewTitle = match ($view) {
    'upcoming' => 'Upcoming',
    'history' => 'History',
    default => 'Active',
};

$emptyMessage = match ($view) {
    'upcoming' => 'No upcoming reminders yet.',
    'history' => 'No notification history yet.',
    default => 'All caught up! Nothing needs your attention right now.',
};

$emptyIcon = match ($view) {
    'upcoming' => 'hourglass',
    'history' => 'archive',
    default => 'sparkles',
};
?>
<section class="page notifications-page">
    <header class="page__header">
        <h1 class="page__title">Notifications</h1>
        <p class="page__lede">Gentle care reminders based on what you have already recorded.</p>
    </header>

    <div class="notif-view-label" aria-live="polite">
        <h2 class="notif-view-label__title"><?= View::e($viewTitle) ?></h2>
        <?php if ($isActive && $attentionCount > 0): ?>
            <span class="notif-view-label__count"><?= $attentionCount ?></span>
        <?php elseif ($isUpcoming && $upcomingCount > 0): ?>
            <span class="notif-view-label__count"><?= $upcomingCount ?></span>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon" aria-hidden="true">
                <i data-lucide="<?= View::e($emptyIcon) ?>"></i>
            </div>
            <p class="empty-state__text"><?= View::e($emptyMessage) ?></p>
        </div>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($items as $n): ?>
                <?php
                $urgency = $isActive ? NotificationPresenter::urgency($n) : null;
                $urgencyLabel = NotificationPresenter::urgencyLabel($urgency);
                $activity = NotificationPresenter::activityName($n);
                $members = $n['members'] ?? [];
                $memberLabel = NotificationPresenter::memberNames($members);
                $dueLabel = NotificationPresenter::formatDate((string) $n['due_date']);
                $timing = NotificationPresenter::timingLabel($n);
                $status = (string) $n['status'];
                $color = (string) ($n['type_color'] ?? '#E4BB93');
                $icon = (string) ($n['type_icon'] ?? 'bell');
                $reminderLabel = NotificationPresenter::formatDate((string) ($n['reminder_start_date'] ?? ''));
                $cardClass = 'notif-card';
                if ($urgency !== null) {
                    $cardClass .= ' notif-card--urgency-' . $urgency;
                }
                ?>
                <li class="<?= View::e($cardClass) ?>">
                    <div class="notif-card__icon" style="--etype-color: <?= View::e($color) ?>">
                        <i data-lucide="<?= View::e($icon) ?>" aria-hidden="true"></i>
                    </div>
                    <div class="notif-card__body">
                        <div class="notif-card__top">
                            <h2 class="notif-card__title"><?= View::e($activity) ?></h2>
                            <?php if ($isHistory): ?>
                                <span class="notif-card__status notif-card__status--<?= View::e($status) ?>">
                                    <?= View::e(NotificationPresenter::statusLabel($status)) ?>
                                </span>
                            <?php else: ?>
                                <span class="notif-card__timing">
                                    <?= View::e($timing) ?>
                                    <?php if ($urgencyLabel !== ''): ?>
                                        <span class="visually-hidden"> · <?= View::e($urgencyLabel) ?></span>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <p class="notif-card__members"><?= View::e($memberLabel) ?></p>

                        <div class="notif-card__meta">
                            <span>Due <?= View::e($dueLabel ?? '') ?></span>
                            <?php if ($isUpcoming && $reminderLabel): ?>
                                <span>Reminds from <?= View::e($reminderLabel) ?></span>
                            <?php endif; ?>
                            <?php if ($isHistory && $status === 'completed'): ?>
                                <span>Completed <?= View::e(NotificationPresenter::formatDateTime($n['completed_at'] ?? null) ?? '') ?></span>
                                <?php if (!empty($n['completion_event_id'])): ?>
                                    <a href="<?= View::e(Url::to('/events/' . (int) $n['completion_event_id'])) ?>">View event</a>
                                <?php endif; ?>
                            <?php elseif ($isHistory && $status === 'disabled'): ?>
                                <span>Disabled <?= View::e(NotificationPresenter::formatDateTime($n['disabled_at'] ?? null) ?? '') ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($members !== []): ?>
                            <ul class="notif-card__avatars" aria-hidden="true">
                                <?php foreach (array_slice($members, 0, 4) as $m): ?>
                                    <?php
                                    $mid = (int) $m['id'];
                                    $photoUrl = !empty($m['photo_cropped'])
                                        ? Url::to('/gang/' . $mid . '/photo/cropped')
                                        : null;
                                    ?>
                                    <li class="notif-card__avatar">
                                        <?php if ($photoUrl): ?>
                                            <img src="<?= View::e($photoUrl) ?>" alt="">
                                        <?php else: ?>
                                            <span><?= View::e(mb_substr((string) $m['name'], 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <?php if (!$isHistory): ?>
                            <div class="notif-card__actions">
                                <a
                                    class="btn btn--primary btn--compact"
                                    href="<?= View::e(Url::to('/events/new?from_notification=' . (int) $n['id'])) ?>"
                                >
                                    This just happened
                                </a>
                                <form
                                    method="post"
                                    action="<?= View::e(Url::to('/notifications/' . (int) $n['id'] . '/disable')) ?>"
                                    data-confirm="Disable this reminder? It will move to history and will not schedule another occurrence."
                                >
                                    <?= Csrf::field() ?>
                                    <button type="submit" class="btn btn--ghost btn--compact">Disable</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <nav class="notif-categories" aria-label="Notification categories">
        <?php if (!$isActive): ?>
            <a class="btn btn--ghost notif-categories__btn" href="<?= View::e(Url::to('/notifications')) ?>">
                Active<?= $attentionCount > 0 ? ' (' . $attentionCount . ')' : '' ?>
            </a>
        <?php endif; ?>

        <?php if (!$isUpcoming): ?>
            <a class="btn btn--ghost notif-categories__btn" href="<?= View::e(Url::to('/notifications?view=upcoming')) ?>">
                Upcoming (<?= $upcomingCount ?>)
            </a>
        <?php endif; ?>

        <?php if (!$isHistory): ?>
            <a class="btn btn--ghost notif-categories__btn" href="<?= View::e(Url::to('/notifications?view=history')) ?>">
                History
            </a>
        <?php endif; ?>
    </nav>
</section>
