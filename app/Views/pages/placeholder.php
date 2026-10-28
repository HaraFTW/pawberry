<?php

use PawBerry\Core\View;

/** @var string $heading */
/** @var string $message */
/** @var string $title */
?>
<section class="page">
    <header class="page__header">
        <h1 class="page__title"><?= View::e($heading ?? $title) ?></h1>
    </header>

    <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">
            <i data-lucide="sparkles"></i>
        </div>
        <p class="empty-state__text"><?= View::e($message) ?></p>
        <p class="empty-state__note">Coming in a later phase.</p>
    </div>
</section>
