<?php

use PawBerry\Core\Config;
use PawBerry\Core\Csrf;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var string $content */
/** @var string $title */

$appName = Config::appName();
$pageTitle = isset($title) ? View::e($title) . ' · ' . View::e($appName) : View::e($appName);
$basePath = Config::basePath();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#342720">
    <title><?= $pageTitle ?></title>
    <link rel="manifest" href="<?= View::e(Url::to('/manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= View::e(Url::asset('icons/paw.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= View::e(Url::asset('css/app.css')) ?>">
    <script>
        window.PawBerry = {
            basePath: <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>,
            csrfToken: <?= json_encode(Csrf::token()) ?>
        };
    </script>
</head>
<body class="guest-body">
    <a class="skip-link" href="#main">Skip to content</a>
    <main id="main" class="guest-main">
        <?= $content ?>
    </main>
    <div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="true"></div>
    <script type="module" src="<?= View::e(Url::asset('js/app.js')) ?>"></script>
</body>
</html>
