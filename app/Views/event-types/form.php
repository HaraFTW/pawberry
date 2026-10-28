<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\EventTypeCatalog;
use PawBerry\Support\EventTypePresenter;

/** @var string $mode */
/** @var array<string, mixed> $type */
/** @var list<array<string, mixed>> $fields */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $mode === 'edit';
$action = $isEdit ? Url::to('/event-types/' . $type['id']) : Url::to('/event-types');

$value = static function (string $key, mixed $default = '') use ($old, $type): string {
    if (array_key_exists($key, $old)) {
        return (string) ($old[$key] ?? '');
    }
    if ($key === 'recurrence_interval' || $key === 'recurrence_unit' || $key === 'reminder_lead_days') {
        return (string) ($type[$key] ?? $default);
    }
    return (string) ($type[$key] ?? $default);
};

$err = static function (string $key) use ($errors): ?string {
    return $errors[$key] ?? null;
};

$icon = $value('icon', 'paw-print');
$color = $value('color', '#E4BB93');
$recurrenceEnabled = array_key_exists('recurrence_enabled', $old)
    ? !empty($old['recurrence_enabled'])
    : EventTypePresenter::isRecurring($type);

$formFields = array_key_exists('fields', $old) && is_array($old['fields'])
    ? $old['fields']
    : $fields;

$icons = EventTypeCatalog::icons();
$palette = EventTypeCatalog::palette();
$fieldTypeLabels = EventTypeCatalog::fieldTypeLabels();
$unitLabels = EventTypeCatalog::recurrenceUnitLabels();

$intervalVal = $value('recurrence_interval', $recurrenceEnabled ? '1' : '');
$unitVal = $value('recurrence_unit', 'months');
$reminderVal = $value('reminder_lead_days', '0');
?>
<section
    class="page form-page etype-form-page"
    data-etype-form
    data-mode="<?= View::e($mode) ?>"
>
    <header class="page__header">
        <a class="profile-back" href="<?= View::e(Url::to('/event-types')) ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span>Event Types</span>
        </a>
        <h1 class="page__title"><?= $isEdit ? 'Edit Event Type' : 'Add Event Type' ?></h1>
        <p class="page__lede">
            <?= $isEdit
                ? 'Tune how this kind of care looks and what details it collects.'
                : 'Define a reusable kind of care activity for The Gang.' ?>
        </p>
    </header>

    <form
        class="etype-form"
        method="post"
        action="<?= View::e($action) ?>"
        novalidate
        data-etype-form-el
    >
        <?= Csrf::field() ?>

        <section class="etype-section" aria-labelledby="etype-basics-title">
            <h2 id="etype-basics-title" class="etype-section__title">Basics</h2>

            <div class="etype-preview" data-etype-preview aria-live="polite">
                <div class="etype-preview__icon" data-preview-icon style="--etype-color: <?= View::e($color) ?>">
                    <i data-lucide="<?= View::e($icon) ?>"></i>
                </div>
                <div>
                    <p class="etype-preview__label">Preview</p>
                    <p class="etype-preview__name" data-preview-name><?= View::e($value('name') !== '' ? $value('name') : 'Event type') ?></p>
                </div>
            </div>

            <div class="field">
                <label class="field__label" for="etype-name">Name</label>
                <input
                    class="input <?= $err('name') ? 'is-invalid' : '' ?>"
                    type="text"
                    id="etype-name"
                    name="name"
                    required
                    maxlength="120"
                    value="<?= View::e($value('name')) ?>"
                    data-etype-name
                    aria-describedby="etype-name-error"
                >
                <p class="field__error" id="etype-name-error" <?= $err('name') ? '' : 'hidden' ?>><?= View::e((string) $err('name')) ?></p>
            </div>

            <div class="field">
                <label class="field__label" for="etype-description">Description <span class="field__optional">optional</span></label>
                <textarea
                    class="input input--area"
                    id="etype-description"
                    name="description"
                    rows="2"
                    maxlength="2000"
                ><?= View::e($value('description')) ?></textarea>
                <p class="field__error" <?= $err('description') ? '' : 'hidden' ?>><?= View::e((string) $err('description')) ?></p>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="etype-look-title">
            <h2 id="etype-look-title" class="etype-section__title">Look</h2>

            <div class="field">
                <p class="field__label" id="etype-icon-label">Icon</p>
                <input type="hidden" name="icon" value="<?= View::e($icon) ?>" data-etype-icon>
                <div class="icon-picker" role="listbox" aria-labelledby="etype-icon-label" data-icon-picker>
                    <?php foreach ($icons as $item): ?>
                        <?php $selected = $item['id'] === $icon; ?>
                        <button
                            type="button"
                            class="icon-picker__btn <?= $selected ? 'is-selected' : '' ?>"
                            role="option"
                            aria-selected="<?= $selected ? 'true' : 'false' ?>"
                            data-icon-id="<?= View::e($item['id']) ?>"
                            title="<?= View::e($item['label']) ?>"
                        >
                            <i data-lucide="<?= View::e($item['id']) ?>" aria-hidden="true"></i>
                            <span class="visually-hidden"><?= View::e($item['label']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="field__error" <?= $err('icon') ? '' : 'hidden' ?>><?= View::e((string) $err('icon')) ?></p>
            </div>

            <div class="field">
                <p class="field__label" id="etype-color-label">Color</p>
                <input type="hidden" name="color" value="<?= View::e($color) ?>" data-etype-color>
                <div class="color-picker" role="listbox" aria-labelledby="etype-color-label" data-color-picker>
                    <?php foreach ($palette as $swatch): ?>
                        <?php $selected = strcasecmp($swatch, $color) === 0; ?>
                        <button
                            type="button"
                            class="color-picker__btn <?= $selected ? 'is-selected' : '' ?>"
                            role="option"
                            aria-selected="<?= $selected ? 'true' : 'false' ?>"
                            data-color="<?= View::e($swatch) ?>"
                            style="--swatch: <?= View::e($swatch) ?>"
                            title="<?= View::e($swatch) ?>"
                        >
                            <span class="visually-hidden"><?= View::e($swatch) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="color-custom">
                    <label class="field__label" for="etype-color-custom">Custom color</label>
                    <input
                        class="color-custom__input <?= $err('color') ? 'is-invalid' : '' ?>"
                        type="color"
                        id="etype-color-custom"
                        value="<?= View::e(strtolower($color)) ?>"
                        data-color-custom
                        aria-describedby="etype-color-custom-hint"
                    >
                    <p class="field__hint" id="etype-color-custom-hint">Pick any color, or choose a preset above.</p>
                </div>
                <p class="field__error" <?= $err('color') ? '' : 'hidden' ?>><?= View::e((string) $err('color')) ?></p>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="etype-repeat-title">
            <h2 id="etype-repeat-title" class="etype-section__title">Repeat</h2>
            <p class="etype-section__hint">Optional default for events created from this type. Reminders come later.</p>

            <label class="check etype-repeat-toggle">
                <input
                    type="checkbox"
                    name="recurrence_enabled"
                    value="1"
                    <?= $recurrenceEnabled ? 'checked' : '' ?>
                    data-recurrence-toggle
                >
                <span>This type repeats on a schedule</span>
            </label>

            <div class="etype-recurrence" data-recurrence-panel <?= $recurrenceEnabled ? '' : 'hidden' ?>>
                <div class="field-row etype-recurrence__row">
                    <div class="field">
                        <label class="field__label" for="etype-interval">Every</label>
                        <input
                            class="input <?= $err('recurrence_interval') ? 'is-invalid' : '' ?>"
                            type="number"
                            id="etype-interval"
                            name="recurrence_interval"
                            min="1"
                            max="999"
                            inputmode="numeric"
                            value="<?= View::e($intervalVal) ?>"
                            <?= $recurrenceEnabled ? '' : 'disabled' ?>
                        >
                        <p class="field__error" <?= $err('recurrence_interval') ? '' : 'hidden' ?>><?= View::e((string) $err('recurrence_interval')) ?></p>
                    </div>
                    <div class="field">
                        <label class="field__label" for="etype-unit">Unit</label>
                        <select
                            class="input <?= $err('recurrence_unit') ? 'is-invalid' : '' ?>"
                            id="etype-unit"
                            name="recurrence_unit"
                            <?= $recurrenceEnabled ? '' : 'disabled' ?>
                        >
                            <?php foreach ($unitLabels as $unit => $label): ?>
                                <option value="<?= View::e($unit) ?>" <?= $unitVal === $unit ? 'selected' : '' ?>><?= View::e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="field__error" <?= $err('recurrence_unit') ? '' : 'hidden' ?>><?= View::e((string) $err('recurrence_unit')) ?></p>
                    </div>
                </div>

                <div class="field">
                    <label class="field__label" for="etype-reminder">Remind me</label>
                    <div class="etype-reminder">
                        <input
                            class="input input--narrow <?= $err('reminder_lead_days') ? 'is-invalid' : '' ?>"
                            type="number"
                            id="etype-reminder"
                            name="reminder_lead_days"
                            min="0"
                            max="365"
                            inputmode="numeric"
                            value="<?= View::e($reminderVal) ?>"
                            <?= $recurrenceEnabled ? '' : 'disabled' ?>
                        >
                        <span class="etype-reminder__suffix">days before the due date</span>
                    </div>
                    <p class="field__hint">Use 0 to remind on the due date itself.</p>
                    <p class="field__error" <?= $err('reminder_lead_days') ? '' : 'hidden' ?>><?= View::e((string) $err('reminder_lead_days')) ?></p>
                </div>
            </div>
        </section>

        <section class="etype-section" aria-labelledby="etype-fields-title">
            <div class="etype-section__head">
                <div>
                    <h2 id="etype-fields-title" class="etype-section__title">Custom fields</h2>
                    <p class="etype-section__hint">Extra details to collect when recording this type of event.</p>
                </div>
                <button type="button" class="btn btn--ghost btn--compact" data-add-field>
                    <i data-lucide="plus" aria-hidden="true"></i>
                    <span>Add field</span>
                </button>
            </div>

            <p class="field__error" data-fields-error <?= $err('fields') ? '' : 'hidden' ?>><?= View::e((string) $err('fields')) ?></p>

            <div class="efield-list" data-field-list>
                <?php if ($formFields === []): ?>
                    <p class="efield-empty" data-fields-empty>No custom fields yet. Add one if you need extra details.</p>
                <?php endif; ?>

                <?php foreach ($formFields as $index => $field): ?>
                    <?php
                    $fid = isset($field['id']) && $field['id'] !== null && $field['id'] !== ''
                        ? (string) $field['id']
                        : '';
                    $fkey = (string) ($field['field_key'] ?? '');
                    $fname = (string) ($field['name'] ?? '');
                    $ftype = (string) ($field['field_type'] ?? 'text');
                    $freq = !empty($field['is_required']);
                    $nameErr = $err('fields.' . $index . '.name');
                    $typeErr = $err('fields.' . $index . '.field_type');
                    ?>
                    <div class="efield" data-field-row>
                        <div class="efield__handle" aria-hidden="true" title="Drag to reorder">
                            <i data-lucide="grip-vertical"></i>
                        </div>
                        <div class="efield__fields">
                            <?php if ($fid !== ''): ?>
                                <input type="hidden" name="fields[<?= (int) $index ?>][id]" value="<?= View::e($fid) ?>">
                            <?php endif; ?>
                            <?php if ($fkey !== ''): ?>
                                <input type="hidden" name="fields[<?= (int) $index ?>][field_key]" value="<?= View::e($fkey) ?>" data-field-key>
                            <?php endif; ?>
                            <div class="field">
                                <label class="field__label">Field name</label>
                                <input
                                    class="input <?= $nameErr ? 'is-invalid' : '' ?>"
                                    type="text"
                                    name="fields[<?= (int) $index ?>][name]"
                                    maxlength="120"
                                    value="<?= View::e($fname) ?>"
                                    required
                                    data-field-name
                                >
                                <p class="field__error" <?= $nameErr ? '' : 'hidden' ?>><?= View::e((string) $nameErr) ?></p>
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label class="field__label">Type</label>
                                    <select class="input <?= $typeErr ? 'is-invalid' : '' ?>" name="fields[<?= (int) $index ?>][field_type]" data-field-type>
                                        <?php foreach ($fieldTypeLabels as $typeKey => $typeLabel): ?>
                                            <option value="<?= View::e($typeKey) ?>" <?= $ftype === $typeKey ? 'selected' : '' ?>><?= View::e($typeLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="field__error" <?= $typeErr ? '' : 'hidden' ?>><?= View::e((string) $typeErr) ?></p>
                                </div>
                                <label class="check efield__required">
                                    <input type="checkbox" name="fields[<?= (int) $index ?>][is_required]" value="1" <?= $freq ? 'checked' : '' ?>>
                                    <span>Required</span>
                                </label>
                            </div>
                        </div>
                        <div class="efield__actions">
                            <button type="button" class="btn btn--ghost btn--compact" data-move-up title="Move up">↑</button>
                            <button type="button" class="btn btn--ghost btn--compact" data-move-down title="Move down">↓</button>
                            <button type="button" class="btn btn--ghost btn--compact" data-remove-field>Remove</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <template data-field-template>
                <div class="efield" data-field-row>
                    <div class="efield__handle" aria-hidden="true" title="Drag to reorder">
                        <i data-lucide="grip-vertical"></i>
                    </div>
                    <div class="efield__fields">
                        <div class="field">
                            <label class="field__label">Field name</label>
                            <input class="input" type="text" name="fields[__i__][name]" maxlength="120" value="" required data-field-name>
                            <p class="field__error" hidden></p>
                        </div>
                        <div class="field-row">
                            <div class="field">
                                <label class="field__label">Type</label>
                                <select class="input" name="fields[__i__][field_type]" data-field-type>
                                    <?php foreach ($fieldTypeLabels as $typeKey => $typeLabel): ?>
                                        <option value="<?= View::e($typeKey) ?>"><?= View::e($typeLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="field__error" hidden></p>
                            </div>
                            <label class="check efield__required">
                                <input type="checkbox" name="fields[__i__][is_required]" value="1">
                                <span>Required</span>
                            </label>
                        </div>
                    </div>
                    <div class="efield__actions">
                        <button type="button" class="btn btn--ghost btn--compact" data-move-up title="Move up">↑</button>
                        <button type="button" class="btn btn--ghost btn--compact" data-move-down title="Move down">↓</button>
                        <button type="button" class="btn btn--ghost btn--compact" data-remove-field>Remove</button>
                    </div>
                </div>
            </template>
        </section>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block" data-submit>
                <span data-submit-label><?= $isEdit ? 'Save changes' : 'Create event type' ?></span>
                <span data-submit-loading hidden>Saving…</span>
            </button>
            <a class="btn btn--ghost btn--block" href="<?= View::e(Url::to('/event-types')) ?>">Cancel</a>
        </div>
    </form>
</section>

<script type="module" src="<?= View::e(Url::asset('js/event-type-form.js')) ?>"></script>
