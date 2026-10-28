<?php

use PawBerry\Core\Config;
use PawBerry\Core\Csrf;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Services\AuthService;
use PawBerry\Support\NotificationPresenter;

/** @var string $content */
/** @var string $title */
/** @var string $nav */

$appName = Config::appName();
$pageTitle = isset($title) ? View::e($title) . ' · ' . View::e($appName) : View::e($appName);
$active = $nav ?? '';
$flashes = Session::pullFlashes();
$basePath = Config::basePath();

$notificationAttentionCount = 0;
$popupPayload = [];
if ((new AuthService())->check()) {
    try {
        $notifRepo = new NotificationRepository();
        $notificationAttentionCount = $notifRepo->countAttention();
        foreach ($notifRepo->popupCandidates(3) as $popup) {
            $popupPayload[] = [
                'id' => (int) $popup['id'],
                'message' => NotificationPresenter::popupMessage($popup),
                'state' => NotificationPresenter::displayState($popup),
                'urgency' => NotificationPresenter::urgency($popup),
                'color' => (string) ($popup['type_color'] ?? '#E4BB93'),
                'icon' => (string) ($popup['type_icon'] ?? 'bell'),
                'href' => Url::to('/notifications'),
            ];
        }
    } catch (Throwable) {
        $notificationAttentionCount = 0;
        $popupPayload = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#342720">
    <meta name="application-name" content="<?= View::e($appName) ?>">
    <title><?= $pageTitle ?></title>
    <link rel="manifest" href="<?= View::e(Url::to('/manifest.webmanifest')) ?>">
    <link rel="icon" href="<?= View::e(Url::asset('icons/paw.svg')) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= View::e(Url::asset('icons/paw.svg')) ?>">
    <link rel="stylesheet" href="<?= View::e(Url::asset('css/app.css')) ?>">
    <script>
        window.PawBerry = {
            basePath: <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>,
            csrfToken: <?= json_encode(Csrf::token()) ?>,
            urls: {
                logout: <?= json_encode(Url::to('/logout')) ?>,
                notifications: <?= json_encode(Url::to('/notifications')) ?>,
                snoozePopup: <?= json_encode(Url::to('/notifications/__ID__/snooze-popup')) ?>,
                snoozeAllPopups: <?= json_encode(Url::to('/notifications/snooze-popups')) ?>
            }
        };
    </script>
</head>
<body class="app-body" data-page="<?= View::e($active) ?>">
    <a class="skip-link" href="#main">Skip to content</a>

    <div class="app-shell">
        <?php require PAWBERRY_ROOT . '/app/Views/partials/sidebar.php'; ?>

        <div class="app-main-column">
            <?php require PAWBERRY_ROOT . '/app/Views/partials/topbar.php'; ?>

            <main id="main" class="app-content">
                <?= $content ?>
            </main>
        </div>

        <?php require PAWBERRY_ROOT . '/app/Views/partials/bottom-nav.php'; ?>
    </div>

    <div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="true"></div>
    <div id="notif-popup-region" class="notif-popup-region" aria-live="polite"></div>

    <script type="application/json" id="boot-flashes"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="application/json" id="boot-notification-popups"><?= json_encode($popupPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
    <script type="module" src="<?= View::e(Url::asset('js/app.js')) ?>"></script>
</body>
</html>
