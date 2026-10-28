<?php

use PawBerry\Core\Config;
use PawBerry\Core\Url;
use PawBerry\Core\View;

/** @var int $status */
/** @var string $title */
/** @var string $message */
/** @var Throwable|null $exception */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#342720">
    <title><?= (int) $status ?> · <?= View::e(Config::appName()) ?></title>
    <link rel="stylesheet" href="<?= View::e(Url::asset('css/app.css')) ?>">
</head>
<body class="guest-body">
    <main class="guest-main">
        <section class="login error-page">
            <div class="login__card">
                <p class="error-page__code"><?= (int) $status ?></p>
                <h1 class="login__name"><?= View::e($title) ?></h1>
                <p class="login__welcome"><?= View::e($message) ?></p>
                <p><a class="btn btn--primary" href="<?= View::e(Url::to('/')) ?>">Back home</a></p>
                <?php if ($exception && Config::isDevelopment()): ?>
                    <pre class="error-page__detail"><?= View::e($exception->getMessage() . "\n" . $exception->getFile() . ':' . $exception->getLine()) ?></pre>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
