<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\MemberPresenter;

/** @var array<string, mixed> $member */

$age = MemberPresenter::ageLabel($member['birth_date'] ?? null);
$birthLabel = MemberPresenter::formatDate($member['birth_date'] ?? null);
$isArchived = MemberPresenter::isArchived($member);
$photoUrl = !empty($member['photo_cropped'])
    ? Url::to('/gang/' . $member['id'] . '/photo/cropped')
    : null;
?>
<section class="page profile-page">
    <header class="profile-hero">
        <a class="profile-back" href="<?= View::e(Url::to('/gang')) ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>The Gang</span>
        </a>

        <div class="profile-hero__photo <?= $photoUrl ? '' : 'profile-hero__photo--empty' ?>">
            <?php if ($photoUrl): ?>
                <img src="<?= View::e($photoUrl) ?>" alt="<?= View::e((string) $member['name']) ?>" width="480" height="480">
            <?php else: ?>
                <div class="member-card__fallback member-card__fallback--lg" aria-hidden="true">
                    <i data-lucide="paw-print"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-hero__text">
            <?php if ($isArchived): ?>
                <p class="profile-badge">Archived</p>
            <?php endif; ?>
            <h1 class="page__title"><?= View::e((string) $member['name']) ?></h1>
            <p class="profile-hero__subtitle">
                <?= View::e((string) $member['species']) ?>
                <?php if (!empty($member['breed'])): ?>
                    <span aria-hidden="true"> · </span><?= View::e((string) $member['breed']) ?>
                <?php endif; ?>
                <?php if ($age !== null): ?>
                    <span aria-hidden="true"> · </span><?= View::e($age) ?>
                <?php endif; ?>
            </p>
        </div>
    </header>

    <div class="profile-actions">
        <a class="btn btn--primary" href="<?= View::e(Url::to('/gang/' . $member['id'] . '/edit')) ?>">
            <i data-lucide="pencil" aria-hidden="true"></i>
            <span>Edit</span>
        </a>
        <?php if ($isArchived): ?>
            <form method="post" action="<?= View::e(Url::to('/gang/' . $member['id'] . '/restore')) ?>">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--ghost">
                    <i data-lucide="archive-restore" aria-hidden="true"></i>
                    <span>Restore</span>
                </button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= View::e(Url::to('/gang/' . $member['id'] . '/archive')) ?>" data-confirm="Archive <?= View::e((string) $member['name']) ?>? They can be restored later.">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn--ghost">
                    <i data-lucide="archive" aria-hidden="true"></i>
                    <span>Archive</span>
                </button>
            </form>
        <?php endif; ?>
    </div>

    <dl class="profile-details">
        <?php if (!empty($member['sex'])): ?>
            <div>
                <dt>Sex</dt>
                <dd><?= View::e((string) $member['sex']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if ($birthLabel !== null): ?>
            <div>
                <dt>Birthday</dt>
                <dd><?= View::e($birthLabel) ?><?= $age ? ' · ' . View::e($age) : '' ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($member['microchip_number'])): ?>
            <div>
                <dt>Microchip</dt>
                <dd><?= View::e((string) $member['microchip_number']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($member['description'])): ?>
            <div class="profile-details__block">
                <dt>About</dt>
                <dd><?= nl2br(View::e((string) $member['description'])) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($member['notes'])): ?>
            <div class="profile-details__block">
                <dt>Notes</dt>
                <dd><?= nl2br(View::e((string) $member['notes'])) ?></dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php
    /** @var list<array<string, mixed>> $recentEvents */
    /** @var int $eventCount */
    $recentEvents = $recentEvents ?? [];
    $eventCount = $eventCount ?? 0;
    ?>
    <section class="profile-history" aria-label="History">
        <div class="profile-history__head">
            <h2 class="profile-history__title">Moments</h2>
            <a class="btn btn--ghost btn--compact" href="<?= View::e(Url::to('/events/new?member=' . $member['id'])) ?>">
                <i data-lucide="plus" aria-hidden="true"></i>
                <span>Add Event</span>
            </a>
        </div>

        <?php if ($recentEvents === []): ?>
            <p class="profile-history__note">No events recorded yet for <?= View::e((string) $member['name']) ?>.</p>
        <?php else: ?>
            <div class="event-timeline event-timeline--compact">
                <?php foreach ($recentEvents as $event): ?>
                    <?php
                    $evTitle = \PawBerry\Support\EventPresenter::displayTitle($event);
                    $evDate = \PawBerry\Support\EventPresenter::formatDate((string) $event['event_date']);
                    ?>
                    <a class="event-card event-card--compact" href="<?= View::e(Url::to('/events/' . $event['id'])) ?>">
                        <div class="event-card__icon" style="--etype-color: <?= View::e((string) $event['type_color']) ?>" aria-hidden="true">
                            <i data-lucide="<?= View::e((string) $event['type_icon']) ?>"></i>
                        </div>
                        <div class="event-card__body">
                            <div class="event-card__top">
                                <h3 class="event-card__title"><?= View::e($evTitle) ?></h3>
                                <?php if ($evDate): ?>
                                    <time class="event-card__date" datetime="<?= View::e((string) $event['event_date']) ?>"><?= View::e($evDate) ?></time>
                                <?php endif; ?>
                            </div>
                            <?php if (count($event['members'] ?? []) > 1): ?>
                                <p class="event-card__members">
                                    Shared with
                                    <?php
                                    $others = [];
                                    foreach ($event['members'] as $em) {
                                        if ((int) $em['id'] !== (int) $member['id']) {
                                            $others[] = (string) $em['name'];
                                        }
                                    }
                                    echo View::e(implode(', ', $others));
                                    ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if ($eventCount > count($recentEvents)): ?>
                <p class="profile-history__more">
                    <a href="<?= View::e(Url::to('/events?member=' . $member['id'])) ?>">
                        View all <?= (int) $eventCount ?> events
                    </a>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</section>
