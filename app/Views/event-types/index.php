<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventTypePresenter;

/** @var list<array<string, mixed>> $types */
/** @var list<array<string, mixed>> $archivedTypes */
/** @var bool $showArchived */
/** @var int $archivedCount */

$renderCard = static function (array $type, bool $archived = false): void {
    $recurrence = EventTypePresenter::recurrenceSummary($type);
    $fieldCount = (int) ($type['field_count'] ?? 0);
    $color = (string) $type['color'];
    $icon = (string) $type['icon'];
    $editUrl = Url::to('/event-types/' . $type['id'] . '/edit');
    ?>
    <article class="etype-card <?= $archived ? 'etype-card--archived' : '' ?>">
        <div class="etype-card__main">
            <div class="etype-card__icon" style="--etype-color: <?= View::e($color) ?>" aria-hidden="true">
                <i data-lucide="<?= View::e($icon) ?>"></i>
            </div>
            <div class="etype-card__body">
                <h2 class="etype-card__name"><?= View::e((string) $type['name']) ?></h2>
                <?php if (!empty($type['description'])): ?>
                    <p class="etype-card__desc"><?= View::e((string) $type['description']) ?></p>
                <?php endif; ?>
                <div class="etype-card__meta">
                    <span class="etype-chip etype-chip--swatch" title="Color">
                        <span class="etype-swatch" style="background: <?= View::e($color) ?>"></span>
                        <span class="visually-hidden">Color <?= View::e($color) ?></span>
                    </span>
                    <?php if ($recurrence !== null): ?>
                        <span class="etype-chip">
                            <i data-lucide="calendar-days" aria-hidden="true"></i>
                            <?= View::e($recurrence) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($fieldCount > 0): ?>
                        <span class="etype-chip">
                            <i data-lucide="clipboard-list" aria-hidden="true"></i>
                            <?= $fieldCount === 1 ? '1 custom field' : $fieldCount . ' custom fields' ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="etype-card__actions">
            <a class="btn btn--ghost btn--compact" href="<?= View::e($editUrl) ?>">
                <i data-lucide="pencil" aria-hidden="true"></i>
                <span>Edit</span>
            </a>
            <?php if ($archived): ?>
                <form method="post" action="<?= View::e(Url::to('/event-types/' . $type['id'] . '/restore')) ?>">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--ghost btn--compact">
                        <i data-lucide="archive-restore" aria-hidden="true"></i>
                        <span>Restore</span>
                    </button>
                </form>
            <?php else: ?>
                <form
                    method="post"
                    action="<?= View::e(Url::to('/event-types/' . $type['id'] . '/archive')) ?>"
                    data-confirm="Archive <?= View::e((string) $type['name']) ?>? You can restore it later."
                >
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn btn--ghost btn--compact">
                        <i data-lucide="archive" aria-hidden="true"></i>
                        <span>Archive</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </article>
    <?php
};
?>
<section class="page etype-page">
    <header class="page__header page__header--split">
        <div>
            <h1 class="page__title">Event Types</h1>
            <p class="page__lede">Shape the kinds of care you want to record for The Gang.</p>
        </div>
        <a class="btn btn--primary" href="<?= View::e(Url::to('/event-types/new')) ?>">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Add Event Type</span>
        </a>
    </header>

    <?php if ($types === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon" aria-hidden="true">
                <i data-lucide="tags"></i>
            </div>
            <p class="empty-state__text">No event types yet.</p>
            <p class="empty-state__note">Create a type like Vet visit or Grooming to get started.</p>
            <p class="empty-state__action">
                <a class="btn btn--primary" href="<?= View::e(Url::to('/event-types/new')) ?>">Add Event Type</a>
            </p>
        </div>
    <?php else: ?>
        <div class="etype-list">
            <?php foreach ($types as $type): ?>
                <?php $renderCard($type, false); ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($archivedCount > 0): ?>
        <div class="archived-panel">
            <?php if (!$showArchived): ?>
                <a class="archived-panel__toggle" href="<?= View::e(Url::to('/event-types?archived=1')) ?>">
                    <i data-lucide="archive" aria-hidden="true"></i>
                    <span>Show archived types (<?= (int) $archivedCount ?>)</span>
                </a>
            <?php else: ?>
                <div class="archived-panel__header">
                    <h2 class="archived-panel__title">Archived</h2>
                    <a class="archived-panel__toggle" href="<?= View::e(Url::to('/event-types')) ?>">Hide archived</a>
                </div>
                <div class="etype-list etype-list--archived">
                    <?php foreach ($archivedTypes as $type): ?>
                        <?php $renderCard($type, true); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
