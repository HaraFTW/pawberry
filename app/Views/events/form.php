<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventPresenter;

/** @var string $mode */
/** @var array<string, mixed>|null $event */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var list<array<string, mixed>> $types */
/** @var list<array<string, mixed>> $members */
/** @var array<int, list<array<string, mixed>>> $fieldsByType */
/** @var list<array<string, mixed>> $existingValues */
/** @var list<array<string, mixed>> $existingAttachments */
/** @var int|null $selectedTypeId */
/** @var list<int> $selectedMemberIds */
/** @var string $today */
/** @var int $completesNotificationId */
/** @var array<string, mixed>|null $completingNotification */

$isEdit = $mode === 'edit';
$isCompleting = !$isEdit && !empty($completesNotificationId);
$action = $isEdit ? Url::to('/events/' . $event['id']) : Url::to('/events');
$completesNotificationId = (int) ($completesNotificationId ?? 0);

$value = static function (string $key, mixed $default = '') use ($old, $event): string {
    if (array_key_exists($key, $old)) {
        return (string) ($old[$key] ?? '');
    }
    if ($event !== null && array_key_exists($key, $event)) {
        return (string) ($event[$key] ?? '');
    }
    return (string) $default;
};

$err = static function (string $key) use ($errors): ?string {
    return $errors[$key] ?? null;
};

$fieldOld = is_array($old['fields'] ?? null) ? $old['fields'] : [];
$valuesByFieldId = [];
foreach ($existingValues as $ev) {
    $valuesByFieldId[(int) $ev['event_type_field_id']] = $ev;
}

$originalTypeId = $isEdit ? (int) $event['event_type_id'] : 0;
$typeValue = $selectedTypeId ?: (int) $value('event_type_id', '0');
$dateValue = $value('event_date', $isEdit ? '' : $today);
$costValue = $value('cost', $isEdit && isset($event['cost']) && $event['cost'] !== null ? (string) $event['cost'] : '');

$fieldsJson = [];
foreach ($fieldsByType as $tid => $fields) {
    $fieldsJson[(string) $tid] = array_map(static function (array $f): array {
        return [
            'id' => (int) $f['id'],
            'name' => (string) $f['name'],
            'field_key' => (string) $f['field_key'],
            'field_type' => (string) $f['field_type'],
            'is_required' => !empty($f['is_required']),
        ];
    }, $fields);
}
?>
<section
    class="page form-page event-form-page"
    data-event-form
    data-mode="<?= View::e($mode) ?>"
    data-original-type="<?= (int) $originalTypeId ?>"
>
    <header class="page__header">
        <a class="profile-back" href="<?= View::e($isEdit ? Url::to('/events/' . $event['id']) : ($isCompleting ? Url::to('/notifications') : Url::to('/events'))) ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span><?= $isEdit ? 'Back to event' : ($isCompleting ? 'Notifications' : 'Events') ?></span>
        </a>
        <h1 class="page__title"><?= $isEdit ? 'Edit Event' : ($isCompleting ? 'Record completion' : 'Add Event') ?></h1>
        <p class="page__lede">
            <?php if ($isEdit): ?>
                Update this care moment.
            <?php elseif ($isCompleting): ?>
                Prefill is ready from the last occurrence — edit anything before saving. Saving marks the reminder done and schedules the next one.
            <?php else: ?>
                Record something that happened for The Gang.
            <?php endif; ?>
        </p>
    </header>

    <form
        class="event-form"
        method="post"
        action="<?= View::e($action) ?>"
        enctype="multipart/form-data"
        novalidate
        data-event-form-el
    >
        <?= Csrf::field() ?>
        <?php if ($isCompleting): ?>
            <input type="hidden" name="completes_notification_id" value="<?= $completesNotificationId ?>">
        <?php endif; ?>

        <?php if ($err('form')): ?>
            <p class="alert alert--danger"><?= View::e($err('form')) ?></p>
        <?php endif; ?>
        <?php if ($err('completes_notification_id')): ?>
            <p class="alert alert--danger"><?= View::e($err('completes_notification_id')) ?></p>
        <?php endif; ?>

        <section class="etype-section" aria-labelledby="event-type-title">
            <h2 id="event-type-title" class="etype-section__title">What happened?</h2>

            <div class="field">
                <p class="field__label" id="event-type-label">Event type</p>
                <div class="event-type-picker" role="listbox" aria-labelledby="event-type-label" data-type-picker>
                    <?php foreach ($types as $type): ?>
                        <?php $tid = (int) $type['id']; $selected = $typeValue === $tid; ?>
                        <label class="event-type-option <?= $selected ? 'is-selected' : '' ?>">
                            <input
                                type="radio"
                                name="event_type_id"
                                value="<?= $tid ?>"
                                <?= $selected ? 'checked' : '' ?>
                                required
                                data-type-radio
                            >
                            <span class="event-type-option__icon" style="--etype-color: <?= View::e((string) $type['color']) ?>" aria-hidden="true">
                                <i data-lucide="<?= View::e((string) $type['icon']) ?>"></i>
                            </span>
                            <span class="event-type-option__name">
                                <?= View::e((string) $type['name']) ?>
                                <?php if (!empty($type['archived_at'])): ?>
                                    <span class="event-detail__archived">archived</span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="field__error" <?= $err('event_type_id') ? '' : 'hidden' ?>><?= View::e((string) $err('event_type_id')) ?></p>
            </div>

            <div class="field" data-type-change-confirm <?= ($err('confirm_type_change') || (!empty($old['type_changed']))) ? '' : 'hidden' ?>>
                <label class="check">
                    <input type="checkbox" name="confirm_type_change" value="1" <?= !empty($old['confirm_type_change']) ? 'checked' : '' ?>>
                    <span>Replace custom field values for the new event type</span>
                </label>
                <p class="field__hint">Changing the type removes values that belonged to the previous type.</p>
                <p class="field__error" <?= $err('confirm_type_change') ? '' : 'hidden' ?>><?= View::e((string) $err('confirm_type_change')) ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="event-date">Date</label>
                <input
                    class="input <?= $err('event_date') ? 'is-invalid' : '' ?>"
                    type="date"
                    id="event-date"
                    name="event_date"
                    required
                    max="<?= View::e($today) ?>"
                    value="<?= View::e($dateValue) ?>"
                >
                <p class="field__error" <?= $err('event_date') ? '' : 'hidden' ?>><?= View::e((string) $err('event_date')) ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="event-title">Title <span class="field__optional">optional</span></label>
                <input class="input <?= $err('title') ? 'is-invalid' : '' ?>" type="text" id="event-title" name="title" maxlength="160" value="<?= View::e($value('title')) ?>" placeholder="Defaults to the event type name">
                <p class="field__error" <?= $err('title') ? '' : 'hidden' ?>><?= View::e((string) $err('title')) ?></p>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="event-members-title">
            <h2 id="event-members-title" class="etype-section__title">Who was involved?</h2>
            <p class="etype-section__hint">Choose one or more members of The Gang.</p>
            <div class="member-picker" data-member-picker>
                <?php foreach ($members as $member): ?>
                    <?php
                    $mid = (int) $member['id'];
                    $checked = in_array($mid, $selectedMemberIds, true);
                    $photoUrl = !empty($member['photo_cropped'])
                        ? Url::to('/gang/' . $mid . '/photo/cropped')
                        : null;
                    ?>
                    <label class="member-picker__option <?= $checked ? 'is-selected' : '' ?>">
                        <input type="checkbox" name="member_ids[]" value="<?= $mid ?>" <?= $checked ? 'checked' : '' ?> data-member-check>
                        <span class="member-picker__photo" aria-hidden="true">
                            <?php if ($photoUrl): ?>
                                <img src="<?= View::e($photoUrl) ?>" alt="" width="64" height="64" loading="lazy">
                            <?php else: ?>
                                <i data-lucide="paw-print"></i>
                            <?php endif; ?>
                        </span>
                        <span class="member-picker__name">
                            <?= View::e((string) $member['name']) ?>
                            <?php if (!empty($member['archived_at'])): ?>
                                <span class="event-detail__archived">archived</span>
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="field__error" <?= $err('member_ids') ? '' : 'hidden' ?>><?= View::e((string) $err('member_ids')) ?></p>
        </section>

        <section class="etype-section" aria-labelledby="event-custom-title" data-custom-fields-section>
            <h2 id="event-custom-title" class="etype-section__title">Custom details</h2>
            <p class="etype-section__hint" data-custom-fields-hint>Choose an event type to see any extra fields.</p>
            <div data-custom-fields></div>
            <div data-historical-fields>
                <?php if ($isEdit && $typeValue === $originalTypeId): ?>
                    <?php foreach ($existingValues as $ev): ?>
                        <?php if (empty($ev['field_deleted_at'])) {
                            continue;
                        } ?>
                        <?php
                        $fkey = (string) $ev['field_key'];
                        $fid = (int) $ev['event_type_field_id'];
                        $ftype = (string) $ev['field_type'];
                        $current = array_key_exists($fkey, $fieldOld)
                            ? $fieldOld[$fkey]
                            : ($ev['value_text'] ?? '');
                        if (is_array($current)) {
                            $current = '';
                        }
                        $current = (string) $current;
                        ?>
                        <div class="field event-historical-field">
                            <label class="field__label">
                                <?= View::e((string) $ev['field_name']) ?>
                                <span class="field__optional">former field</span>
                            </label>
                            <?php if ($ftype === 'checkbox'): ?>
                                <label class="check">
                                    <input type="hidden" name="fields[<?= View::e($fkey) ?>]" value="0">
                                    <input type="checkbox" name="fields[<?= View::e($fkey) ?>]" value="1" <?= $current === '1' ? 'checked' : '' ?>>
                                    <span>Yes</span>
                                </label>
                            <?php elseif ($ftype === 'rating'): ?>
                                <select class="input" name="fields[<?= View::e($fkey) ?>]">
                                    <option value="">—</option>
                                    <?php for ($r = 1; $r <= 5; $r++): ?>
                                        <option value="<?= $r ?>" <?= $current === (string) $r ? 'selected' : '' ?>><?= $r ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php elseif ($ftype === 'date'): ?>
                                <input class="input" type="date" name="fields[<?= View::e($fkey) ?>]" value="<?= View::e($current) ?>">
                            <?php elseif ($ftype === 'number'): ?>
                                <input class="input" type="number" step="any" name="fields[<?= View::e($fkey) ?>]" value="<?= View::e($current) ?>">
                            <?php else: ?>
                                <input class="input" type="text" name="fields[<?= View::e($fkey) ?>]" value="<?= View::e($current) ?>" maxlength="2000">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="event-more-title">
            <h2 id="event-more-title" class="etype-section__title">Notes & cost</h2>
            <div class="field">
                <label class="field__label" for="event-notes">Notes <span class="field__optional">optional</span></label>
                <textarea class="input input--area <?= $err('notes') ? 'is-invalid' : '' ?>" id="event-notes" name="notes" rows="4" maxlength="10000"><?= View::e($value('notes')) ?></textarea>
                <p class="field__error" <?= $err('notes') ? '' : 'hidden' ?>><?= View::e((string) $err('notes')) ?></p>
            </div>
            <div class="field">
                <label class="field__label" for="event-cost">Cost <span class="field__optional">optional</span></label>
                <input class="input input--narrow <?= $err('cost') ? 'is-invalid' : '' ?>" type="text" inputmode="decimal" id="event-cost" name="cost" value="<?= View::e($costValue) ?>" placeholder="0.00">
                <p class="field__error" <?= $err('cost') ? '' : 'hidden' ?>><?= View::e((string) $err('cost')) ?></p>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="event-files-title">
            <h2 id="event-files-title" class="etype-section__title">Attachments</h2>
            <p class="etype-section__hint">JPEG, PNG, WebP, or PDF · up to 10 MB each · max 12 files</p>

            <?php if ($existingAttachments !== []): ?>
                <ul class="event-attachments event-attachments--edit">
                    <?php foreach ($existingAttachments as $att): ?>
                        <li class="event-attachments__item">
                            <div class="event-attachments__meta">
                                <span><?= View::e((string) $att['original_filename']) ?></span>
                                <p><?= View::e(number_format(((int) $att['file_size']) / 1024, 1)) ?> KB</p>
                            </div>
                            <label class="check">
                                <input type="checkbox" name="remove_attachments[]" value="<?= (int) $att['id'] ?>">
                                <span>Remove</span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="field">
                <label class="btn btn--ghost btn--file">
                    <i data-lucide="image-plus" aria-hidden="true"></i>
                    <span>Add files</span>
                    <input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,application/pdf" multiple hidden data-attachments-input>
                </label>
                <p class="field__hint" data-attachments-summary hidden></p>
                <p class="field__error" <?= $err('attachments') ? '' : 'hidden' ?>><?= View::e((string) $err('attachments')) ?></p>
            </div>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block" data-submit>
                <span data-submit-label><?= $isEdit ? 'Save changes' : ($isCompleting ? 'Save Completion' : 'Save event') ?></span>
                <span data-submit-loading hidden>Saving…</span>
            </button>
            <a class="btn btn--ghost btn--block" href="<?= View::e($isEdit ? Url::to('/events/' . $event['id']) : ($isCompleting ? Url::to('/notifications') : Url::to('/events'))) ?>">Cancel</a>
        </div>
    </form>
</section>

<script type="application/json" id="event-fields-data"><?= json_encode([
    'fieldsByType' => $fieldsJson,
    'oldFields' => $fieldOld,
    'existingValues' => array_map(static function (array $ev): array {
        return [
            'id' => (int) $ev['event_type_field_id'],
            'field_key' => (string) $ev['field_key'],
            'value' => $ev['value_text'] !== null ? (string) $ev['value_text'] : '',
            'deleted' => !empty($ev['field_deleted_at']),
        ];
    }, $existingValues),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<script type="module" src="<?= View::e(Url::asset('js/event-form.js')) ?>"></script>
