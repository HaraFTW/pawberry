<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventPresenter;

/** @var array<string, mixed> $event */
/** @var list<array<string, mixed>> $fieldValues */
/** @var list<array<string, mixed>> $attachments */
/** @var array{needs_history_confirm: bool, history_count: int} $deletionImpact */

$title = EventPresenter::displayTitle($event);
$dateLabel = EventPresenter::formatDate((string) $event['event_date']);
$costLabel = EventPresenter::formatCost($event['cost'] ?? null);
$hasCustomTitle = trim((string) ($event['title'] ?? '')) !== '';
$needsHistoryConfirm = !empty($deletionImpact['needs_history_confirm']);
$historyCount = (int) ($deletionImpact['history_count'] ?? 0);
$deleteConfirm = $needsHistoryConfirm
    ? 'This event is linked to ' . $historyCount . ' notification history record(s). Deleting it will also remove that history. Continue?'
    : 'Delete this event? Attachments will be removed too.';
?>
<section class="page event-detail-page">
    <header class="page__header">
        <a class="profile-back" href="<?= View::e(Url::to('/events')) ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Events</span>
        </a>

        <div class="event-detail__hero">
            <div class="event-detail__icon" style="--etype-color: <?= View::e((string) $event['type_color']) ?>" aria-hidden="true">
                <i data-lucide="<?= View::e((string) $event['type_icon']) ?>"></i>
            </div>
            <div>
                <h1 class="page__title"><?= View::e($title) ?></h1>
                <p class="event-detail__subtitle">
                    <?= View::e((string) $event['type_name']) ?>
                    <?php if ($dateLabel): ?>
                        <span aria-hidden="true"> · </span>
                        <time datetime="<?= View::e((string) $event['event_date']) ?>"><?= View::e($dateLabel) ?></time>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </header>

    <div class="profile-actions">
        <a class="btn btn--primary" href="<?= View::e(Url::to('/events/' . $event['id'] . '/edit')) ?>">
            <i data-lucide="pencil" aria-hidden="true"></i>
            <span>Edit</span>
        </a>
        <form
            method="post"
            action="<?= View::e(Url::to('/events/' . $event['id'] . '/delete')) ?>"
            data-confirm="<?= View::e($deleteConfirm) ?>"
        >
            <?= Csrf::field() ?>
            <?php if ($needsHistoryConfirm): ?>
                <input type="hidden" name="confirm_history_delete" value="1">
            <?php endif; ?>
            <button type="submit" class="btn btn--ghost">
                <i data-lucide="archive" aria-hidden="true"></i>
                <span>Delete</span>
            </button>
        </form>
    </div>

    <dl class="profile-details">
        <?php if ($hasCustomTitle): ?>
            <div>
                <dt>Type</dt>
                <dd><?= View::e((string) $event['type_name']) ?></dd>
            </div>
        <?php endif; ?>
        <?php if ($dateLabel): ?>
            <div>
                <dt>Date</dt>
                <dd><time datetime="<?= View::e((string) $event['event_date']) ?>"><?= View::e($dateLabel) ?></time></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['members'])): ?>
            <div>
                <dt>Members</dt>
                <dd class="event-detail__member-list">
                    <?php foreach ($event['members'] as $i => $m): ?>
                        <?php if ($i > 0): ?><span aria-hidden="true">, </span><?php endif; ?>
                        <a href="<?= View::e(Url::to('/gang/' . $m['id'])) ?>"><?= View::e((string) $m['name']) ?></a>
                        <?php if (!empty($m['archived_at'])): ?>
                            <span class="event-detail__archived">(archived)</span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </dd>
            </div>
        <?php endif; ?>
        <?php if ($costLabel !== null): ?>
            <div>
                <dt>Cost</dt>
                <dd><?= View::e($costLabel) ?></dd>
            </div>
        <?php endif; ?>
        <?php if (!empty($event['notes'])): ?>
            <div class="profile-details__block">
                <dt>Notes</dt>
                <dd><?= nl2br(View::e((string) $event['notes'])) ?></dd>
            </div>
        <?php endif; ?>
    </dl>

    <?php if ($fieldValues !== []): ?>
        <section class="event-detail__section" aria-labelledby="event-fields-title">
            <h2 id="event-fields-title" class="event-detail__section-title">Details</h2>
            <dl class="profile-details">
                <?php foreach ($fieldValues as $fv): ?>
                    <div>
                        <dt>
                            <?= View::e((string) $fv['field_name']) ?>
                            <?php if (!empty($fv['field_deleted_at'])): ?>
                                <span class="event-detail__archived">former field</span>
                            <?php endif; ?>
                        </dt>
                        <dd><?= View::e(EventPresenter::formatFieldValue($fv)) ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if ($attachments !== []): ?>
        <section class="event-detail__section" aria-labelledby="event-files-title">
            <h2 id="event-files-title" class="event-detail__section-title">Attachments</h2>
            <ul class="event-attachments">
                <?php foreach ($attachments as $att): ?>
                    <?php
                    $url = Url::to('/events/' . $event['id'] . '/attachments/' . $att['id']);
                    $isImage = str_starts_with((string) $att['mime_type'], 'image/');
                    ?>
                    <li class="event-attachments__item">
                        <?php if ($isImage): ?>
                            <a class="event-attachments__thumb" href="<?= View::e($url) ?>" target="_blank" rel="noopener">
                                <img src="<?= View::e($url) ?>" alt="" loading="lazy">
                            </a>
                        <?php else: ?>
                            <div class="event-attachments__file-icon" aria-hidden="true">
                                <i data-lucide="clipboard-list"></i>
                            </div>
                        <?php endif; ?>
                        <div class="event-attachments__meta">
                            <a href="<?= View::e($url) ?>" target="_blank" rel="noopener"><?= View::e((string) $att['original_filename']) ?></a>
                            <p><?= View::e(number_format(((int) $att['file_size']) / 1024, 1)) ?> KB</p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</section>
