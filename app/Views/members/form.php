<?php

use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $mode */
/** @var array<string, mixed> $member */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

$isEdit = $mode === 'edit';
$action = $isEdit ? Url::to('/gang/' . $member['id']) : Url::to('/gang');
$value = static function (string $key) use ($old, $member): string {
    if (array_key_exists($key, $old)) {
        return (string) ($old[$key] ?? '');
    }
    return (string) ($member[$key] ?? '');
};
$err = static function (string $key) use ($errors): ?string {
    return $errors[$key] ?? null;
};
$hasPhoto = !empty($member['photo_cropped']);
$photoUrl = $hasPhoto ? Url::to('/gang/' . $member['id'] . '/photo/cropped') : null;
$originalUrl = !empty($member['photo_original'])
    ? Url::to('/gang/' . $member['id'] . '/photo/original')
    : null;
?>
<section class="page form-page" data-member-form data-mode="<?= View::e($mode) ?>">
    <header class="page__header">
        <a class="profile-back" href="<?= View::e($isEdit ? Url::to('/gang/' . $member['id']) : Url::to('/gang')) ?>">
            <i data-lucide="arrow-left" aria-hidden="true"></i>
            <span><?= $isEdit ? 'Back to profile' : 'The Gang' ?></span>
        </a>
        <h1 class="page__title"><?= $isEdit ? 'Edit Member' : 'Add Member' ?></h1>
        <p class="page__lede"><?= $isEdit ? 'Update their details and photograph.' : 'Welcome someone new into The Gang.' ?></p>
    </header>

    <form
        class="member-form"
        method="post"
        action="<?= View::e($action) ?>"
        enctype="multipart/form-data"
        novalidate
        data-member-form-el
    >
        <?= Csrf::field() ?>
        <input type="hidden" name="crop_data" value="" data-crop-data>
        <input type="hidden" name="recrop_only" value="0" data-recrop-only>

        <div class="photo-field" data-photo-field>
            <p class="field__label">Photograph</p>
            <div class="photo-field__preview">
                <div class="photo-field__frame" data-photo-preview>
                    <?php if ($photoUrl): ?>
                        <img src="<?= View::e($photoUrl) ?>" alt="" data-existing-photo>
                    <?php else: ?>
                        <div class="member-card__fallback" aria-hidden="true" data-photo-placeholder>
                            <i data-lucide="camera"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="photo-field__actions">
                    <label class="btn btn--primary btn--file">
                        <i data-lucide="image-plus" aria-hidden="true"></i>
                        <span data-photo-pick-label><?= $hasPhoto ? 'Replace photo' : 'Choose photo' ?></span>
                        <input
                            type="file"
                            name="photo"
                            accept="image/jpeg,image/png,image/webp"
                            capture="environment"
                            data-photo-input
                            hidden
                        >
                    </label>
                    <?php if ($originalUrl): ?>
                        <button
                            type="button"
                            class="btn btn--ghost"
                            data-recrop-btn
                            data-original-url="<?= View::e($originalUrl) ?>"
                        >
                            <i data-lucide="crop" aria-hidden="true"></i>
                            <span>Recrop</span>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn--ghost" data-clear-photo hidden>
                        <span>Remove new crop</span>
                    </button>
                </div>
                <p class="field__hint">JPEG, PNG, or WebP · up to 5 MB · square crop</p>
                <p class="field__error" data-error-for="photo" <?= $err('photo') ? '' : 'hidden' ?>><?= View::e((string) $err('photo')) ?></p>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="name">Name</label>
            <input class="input <?= $err('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name" required maxlength="120" autocomplete="nickname" value="<?= View::e($value('name')) ?>" aria-describedby="name-error">
            <p class="field__error" id="name-error" <?= $err('name') ? '' : 'hidden' ?>><?= View::e((string) $err('name')) ?></p>
        </div>

        <div class="field">
            <label class="field__label" for="species">Species</label>
            <input class="input <?= $err('species') ? 'is-invalid' : '' ?>" type="text" id="species" name="species" required maxlength="120" placeholder="Dog, cat, rabbit…" list="species-suggestions" value="<?= View::e($value('species')) ?>" aria-describedby="species-error">
            <datalist id="species-suggestions">
                <option value="Dog"></option>
                <option value="Cat"></option>
                <option value="Rabbit"></option>
                <option value="Bird"></option>
                <option value="Horse"></option>
            </datalist>
            <p class="field__error" id="species-error" <?= $err('species') ? '' : 'hidden' ?>><?= View::e((string) $err('species')) ?></p>
        </div>

        <div class="field">
            <label class="field__label" for="breed">Breed <span class="field__optional">optional</span></label>
            <input class="input" type="text" id="breed" name="breed" maxlength="120" value="<?= View::e($value('breed')) ?>">
            <p class="field__error" <?= $err('breed') ? '' : 'hidden' ?>><?= View::e((string) $err('breed')) ?></p>
        </div>

        <div class="field-row">
            <div class="field">
                <label class="field__label" for="sex">Sex <span class="field__optional">optional</span></label>
                <select class="input" id="sex" name="sex">
                    <?php
                    $sex = $value('sex');
                    $sexOptions = ['' => 'Prefer not to say', 'Female' => 'Female', 'Male' => 'Male', 'Unknown' => 'Unknown'];
                    foreach ($sexOptions as $opt => $label):
                    ?>
                        <option value="<?= View::e($opt) ?>" <?= $sex === $opt ? 'selected' : '' ?>><?= View::e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label class="field__label" for="birth_date">Birth date <span class="field__optional">optional</span></label>
                <input class="input <?= $err('birth_date') ? 'is-invalid' : '' ?>" type="date" id="birth_date" name="birth_date" max="<?= View::e(date('Y-m-d')) ?>" value="<?= View::e($value('birth_date')) ?>">
                <p class="field__error" <?= $err('birth_date') ? '' : 'hidden' ?>><?= View::e((string) $err('birth_date')) ?></p>
            </div>
        </div>

        <div class="field">
            <label class="field__label" for="microchip_number">Microchip <span class="field__optional">optional</span></label>
            <input class="input" type="text" id="microchip_number" name="microchip_number" maxlength="64" inputmode="text" autocomplete="off" value="<?= View::e($value('microchip_number')) ?>">
            <p class="field__error" <?= $err('microchip_number') ? '' : 'hidden' ?>><?= View::e((string) $err('microchip_number')) ?></p>
        </div>

        <div class="field">
            <label class="field__label" for="description">About them <span class="field__optional">optional</span></label>
            <textarea class="input input--area" id="description" name="description" rows="3" maxlength="5000"><?= View::e($value('description')) ?></textarea>
            <p class="field__error" <?= $err('description') ? '' : 'hidden' ?>><?= View::e((string) $err('description')) ?></p>
        </div>

        <div class="field">
            <label class="field__label" for="notes">Notes <span class="field__optional">optional</span></label>
            <textarea class="input input--area" id="notes" name="notes" rows="3" maxlength="5000"><?= View::e($value('notes')) ?></textarea>
            <p class="field__error" <?= $err('notes') ? '' : 'hidden' ?>><?= View::e((string) $err('notes')) ?></p>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn--primary btn--block" data-submit>
                <span data-submit-label><?= $isEdit ? 'Save changes' : 'Add to The Gang' ?></span>
                <span data-submit-loading hidden>Saving…</span>
            </button>
            <a class="btn btn--ghost btn--block" href="<?= View::e($isEdit ? Url::to('/gang/' . $member['id']) : Url::to('/gang')) ?>">Cancel</a>
        </div>
    </form>
</section>

<div class="pb-cropper" data-cropper-modal hidden>
    <div class="pb-cropper__dialog" role="dialog" aria-modal="true" aria-labelledby="cropper-title">
        <header class="pb-cropper__header">
            <h2 id="cropper-title">Crop photograph</h2>
            <p>Drag to reposition · scroll or pinch to zoom</p>
        </header>
        <div class="pb-cropper__stage-wrap">
            <div class="pb-cropper__stage" data-cropper-stage>
                <img data-cropper-image alt="">
            </div>
        </div>
        <div class="pb-cropper__actions">
            <button type="button" class="btn btn--ghost" data-cropper-cancel>Cancel</button>
            <button type="button" class="btn btn--primary" data-cropper-confirm>Use crop</button>
        </div>
    </div>
</div>

<link rel="stylesheet" href="<?= View::e(Url::asset('vendor/cropperjs/cropper.min.css')) ?>">
<script src="<?= View::e(Url::asset('vendor/cropperjs/cropper.min.js')) ?>" defer></script>
<script type="module" src="<?= View::e(Url::asset('js/member-form.js')) ?>"></script>
