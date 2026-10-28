<?php

use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Support\MemberPresenter;

/** @var list<array<string, mixed>> $members */
/** @var list<array<string, mixed>> $archivedMembers */
/** @var bool $showArchived */
/** @var int $archivedCount */
?>
<section class="page gang-page">
    <header class="page__header page__header--split">
        <div>
            <h1 class="page__title">The Gang</h1>
            <p class="page__lede">Your beloved members, all in one cozy place.</p>
        </div>
        <a class="btn btn--primary" href="<?= View::e(Url::to('/gang/new')) ?>">
            <i data-lucide="plus" aria-hidden="true"></i>
            <span>Add Member</span>
        </a>
    </header>

    <?php if ($members === []): ?>
        <div class="empty-state">
            <div class="empty-state__icon" aria-hidden="true">
                <i data-lucide="paw-print"></i>
            </div>
            <p class="empty-state__text">The Gang is waiting for its first member.</p>
            <p class="empty-state__note">Add a pet to begin their little journal.</p>
            <p class="empty-state__action">
                <a class="btn btn--primary" href="<?= View::e(Url::to('/gang/new')) ?>">Add Member</a>
            </p>
        </div>
    <?php else: ?>
        <div class="member-grid">
            <?php foreach ($members as $member): ?>
                <?php
                $age = MemberPresenter::ageLabel($member['birth_date'] ?? null);
                $photoUrl = !empty($member['photo_cropped'])
                    ? Url::to('/gang/' . $member['id'] . '/photo/cropped')
                    : null;
                ?>
                <a class="member-card" href="<?= View::e(Url::to('/gang/' . $member['id'])) ?>">
                    <div class="member-card__photo">
                        <?php if ($photoUrl): ?>
                            <img src="<?= View::e($photoUrl) ?>" alt="" width="320" height="320" loading="lazy">
                        <?php else: ?>
                            <div class="member-card__fallback" aria-hidden="true">
                                <i data-lucide="paw-print"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="member-card__body">
                        <h2 class="member-card__name"><?= View::e((string) $member['name']) ?></h2>
                        <p class="member-card__meta">
                            <?= View::e((string) $member['species']) ?>
                            <?php if (!empty($member['breed'])): ?>
                                <span aria-hidden="true"> · </span><?= View::e((string) $member['breed']) ?>
                            <?php endif; ?>
                        </p>
                        <?php if ($age !== null): ?>
                            <p class="member-card__age"><?= View::e($age) ?></p>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($archivedCount > 0): ?>
        <div class="archived-panel">
            <?php if (!$showArchived): ?>
                <a class="archived-panel__toggle" href="<?= View::e(Url::to('/gang?archived=1')) ?>">
                    <i data-lucide="archive" aria-hidden="true"></i>
                    <span>Show archived members (<?= (int) $archivedCount ?>)</span>
                </a>
            <?php else: ?>
                <div class="archived-panel__header">
                    <h2 class="archived-panel__title">Archived</h2>
                    <a class="archived-panel__toggle" href="<?= View::e(Url::to('/gang')) ?>">Hide archived</a>
                </div>
                <div class="member-grid member-grid--archived">
                    <?php foreach ($archivedMembers as $member): ?>
                        <?php
                        $photoUrl = !empty($member['photo_cropped'])
                            ? Url::to('/gang/' . $member['id'] . '/photo/cropped')
                            : null;
                        ?>
                        <a class="member-card member-card--archived" href="<?= View::e(Url::to('/gang/' . $member['id'])) ?>">
                            <div class="member-card__photo">
                                <?php if ($photoUrl): ?>
                                    <img src="<?= View::e($photoUrl) ?>" alt="" width="320" height="320" loading="lazy">
                                <?php else: ?>
                                    <div class="member-card__fallback" aria-hidden="true">
                                        <i data-lucide="paw-print"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="member-card__body">
                                <h2 class="member-card__name"><?= View::e((string) $member['name']) ?></h2>
                                <p class="member-card__meta"><?= View::e((string) $member['species']) ?></p>
                                <p class="member-card__age">Archived</p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
