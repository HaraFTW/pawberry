<?php

use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventPresenter;
use PawBerry\Support\NotificationPresenter;

/** @var list<array<string, mixed>> $members */
/** @var list<array<string, mixed>> $needsAttention */
/** @var int $attentionCount */
/** @var list<array<string, mixed>> $recentEvents */
/** @var list<array<string, mixed>> $upcoming */
/** @var string $todayLabel */

$members = $members ?? [];
$needsAttention = $needsAttention ?? [];
$recentEvents = $recentEvents ?? [];
$upcoming = $upcoming ?? [];
$attentionCount = (int) ($attentionCount ?? 0);
?>
<section class="page dashboard-page" data-dashboard>
    <header class="dash-welcome dash-section" data-dash-section>
        <div class="dash-welcome__icon" aria-hidden="true">
            <i data-lucide="heart"></i>
        </div>
        <div class="dash-welcome__text">
            <p class="dash-welcome__hello">Hello, pet parent!</p>
            <h1 class="dash-welcome__title">Here's what's happening with The Gang.</h1>
            <p class="dash-welcome__date">
                <time datetime="<?= View::e(date('Y-m-d')) ?>"><?= View::e($todayLabel) ?></time>
            </p>
        </div>
    </header>

    <section class="dash-section" aria-labelledby="dash-gang-heading" data-dash-section>
        <div class="dash-section__head">
            <h2 id="dash-gang-heading" class="dash-section__title">The Gang</h2>
            <a class="dash-section__link" href="<?= View::e(Url::to('/gang')) ?>">See all</a>
        </div>

        <?php if ($members === []): ?>
            <div class="empty-state empty-state--compact">
                <div class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="paw-print"></i>
                </div>
                <p class="empty-state__text">The Gang is waiting for its first member.</p>
                <p class="empty-state__action">
                    <a class="btn btn--primary btn--compact" href="<?= View::e(Url::to('/gang/new')) ?>">Add Member</a>
                </p>
            </div>
        <?php else: ?>
            <ul class="dash-gang">
                <?php foreach ($members as $member): ?>
                    <?php
                    $mid = (int) $member['id'];
                    $photoUrl = !empty($member['photo_cropped'])
                        ? Url::to('/gang/' . $mid . '/photo/cropped')
                        : null;
                    ?>
                    <li>
                        <a class="dash-gang__card" href="<?= View::e(Url::to('/gang/' . $mid)) ?>">
                            <span class="dash-gang__photo" aria-hidden="true">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= View::e($photoUrl) ?>" alt="" width="96" height="96" loading="lazy">
                                <?php else: ?>
                                    <i data-lucide="paw-print"></i>
                                <?php endif; ?>
                            </span>
                            <span class="dash-gang__name"><?= View::e((string) $member['name']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dash-section" aria-labelledby="dash-attention-heading" data-dash-section>
        <div class="dash-section__head">
            <h2 id="dash-attention-heading" class="dash-section__title">
                Needs attention
                <?php if ($attentionCount > 0): ?>
                    <span class="dash-section__count"><?= $attentionCount ?></span>
                <?php endif; ?>
            </h2>
            <a class="dash-section__link" href="<?= View::e(Url::to('/notifications')) ?>">Notifications</a>
        </div>

        <?php if ($needsAttention === []): ?>
            <div class="dash-positive">
                <i data-lucide="sparkles" aria-hidden="true"></i>
                <p>All caught up — nothing needs attention right now.</p>
            </div>
        <?php else: ?>
            <ul class="dash-attn">
                <?php foreach ($needsAttention as $n): ?>
                    <?php
                    $urgency = NotificationPresenter::urgency($n);
                    $urgencyLabel = NotificationPresenter::urgencyLabel($urgency);
                    $color = (string) ($n['type_color'] ?? '#E4BB93');
                    $icon = (string) ($n['type_icon'] ?? 'bell');
                    $itemClass = 'dash-attn__item';
                    if ($urgency !== null) {
                        $itemClass .= ' dash-attn__item--urgency-' . $urgency;
                    }
                    ?>
                    <li class="<?= View::e($itemClass) ?>">
                        <a class="dash-attn__link" href="<?= View::e(Url::to('/notifications')) ?>">
                            <span class="dash-attn__icon" style="--etype-color: <?= View::e($color) ?>" aria-hidden="true">
                                <i data-lucide="<?= View::e($icon) ?>"></i>
                            </span>
                            <span class="dash-attn__body">
                                <span class="dash-attn__title"><?= View::e(NotificationPresenter::activityName($n)) ?></span>
                                <span class="dash-attn__meta">
                                    <?= View::e(NotificationPresenter::memberNames($n['members'] ?? [])) ?>
                                    <span aria-hidden="true"> · </span>
                                    <span class="dash-attn__timing"><?= View::e(NotificationPresenter::timingLabel($n)) ?></span>
                                    <?php if ($urgencyLabel !== ''): ?>
                                        <span class="visually-hidden"> · <?= View::e($urgencyLabel) ?></span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="dash-section dash-section--cta" data-dash-section>
        <a class="btn btn--primary btn--block dash-quick-add" href="<?= View::e(Url::to('/events/new')) ?>">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Record a new moment</span>
        </a>
    </section>

    <section class="dash-section" aria-labelledby="dash-recent-heading" data-dash-section>
        <div class="dash-section__head">
            <h2 id="dash-recent-heading" class="dash-section__title">Recent moments</h2>
            <a class="dash-section__link" href="<?= View::e(Url::to('/events')) ?>">All events</a>
        </div>

        <?php if ($recentEvents === []): ?>
            <div class="empty-state empty-state--compact">
                <div class="empty-state__icon" aria-hidden="true">
                    <i data-lucide="notebook-pen"></i>
                </div>
                <p class="empty-state__text">No moments recorded yet.</p>
                <p class="empty-state__action">
                    <a class="btn btn--primary btn--compact" href="<?= View::e(Url::to('/events/new')) ?>">Add Event</a>
                </p>
            </div>
        <?php else: ?>
            <ul class="dash-moments">
                <?php foreach ($recentEvents as $event): ?>
                    <?php
                    $eid = (int) $event['id'];
                    $dateLabel = EventPresenter::formatDate((string) $event['event_date']);
                    ?>
                    <li>
                        <a class="dash-moment" href="<?= View::e(Url::to('/events/' . $eid)) ?>">
                            <span class="dash-moment__icon" style="--etype-color: <?= View::e((string) $event['type_color']) ?>" aria-hidden="true">
                                <i data-lucide="<?= View::e((string) $event['type_icon']) ?>"></i>
                            </span>
                            <span class="dash-moment__body">
                                <span class="dash-moment__title"><?= View::e(EventPresenter::displayTitle($event)) ?></span>
                                <span class="dash-moment__meta">
                                    <?= View::e(NotificationPresenter::memberNames($event['members'] ?? [])) ?>
                                    <?php if ($dateLabel): ?>
                                        <span aria-hidden="true"> · </span>
                                        <time datetime="<?= View::e((string) $event['event_date']) ?>"><?= View::e($dateLabel) ?></time>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <?php if ($upcoming !== []): ?>
        <section class="dash-section" aria-labelledby="dash-upcoming-heading" data-dash-section>
            <div class="dash-section__head">
                <h2 id="dash-upcoming-heading" class="dash-section__title">Upcoming</h2>
                <a class="dash-section__link" href="<?= View::e(Url::to('/notifications?view=upcoming')) ?>">Notifications</a>
            </div>
            <ul class="dash-upcoming">
                <?php foreach ($upcoming as $n): ?>
                    <li class="dash-upcoming__item">
                        <a class="dash-upcoming__link" href="<?= View::e(Url::to('/notifications?view=upcoming')) ?>">
                            <span class="dash-upcoming__icon" style="--etype-color: <?= View::e((string) ($n['type_color'] ?? '#E4BB93')) ?>" aria-hidden="true">
                                <i data-lucide="<?= View::e((string) ($n['type_icon'] ?? 'bell')) ?>"></i>
                            </span>
                            <span class="dash-upcoming__body">
                                <span class="dash-upcoming__title"><?= View::e(NotificationPresenter::activityName($n)) ?></span>
                                <span class="dash-upcoming__meta">
                                    <?= View::e(NotificationPresenter::memberNames($n['members'] ?? [])) ?>
                                    <span aria-hidden="true"> · </span>
                                    Due <?= View::e(NotificationPresenter::formatDate((string) $n['due_date']) ?? '') ?>
                                </span>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
