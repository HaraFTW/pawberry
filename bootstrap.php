<?php

declare(strict_types=1);

define('PAWBERRY_ROOT', __DIR__);
define('PAWBERRY_START', microtime(true));

spl_autoload_register(static function (string $class): void {
    $prefix = 'PawBerry\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = PAWBERRY_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

$configApp = PAWBERRY_ROOT . '/config/app.php';
$configDb = PAWBERRY_ROOT . '/config/database.php';

if (!is_file($configApp) || !is_file($configDb)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "PawBerry is not configured.\n";
    echo "Copy config/app.php.example to config/app.php\n";
    echo "and config/database.php.example to config/database.php.\n";
    exit(1);
}

/** @var array<string, mixed> $appConfig */
$appConfig = require $configApp;
/** @var array<string, mixed> $dbConfig */
$dbConfig = require $configDb;

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

$storagePath = $appConfig['storage_path'] ?? null;
if (!is_string($storagePath) || $storagePath === '') {
    $storagePath = PAWBERRY_ROOT . '/storage';
}
define('PAWBERRY_STORAGE', $storagePath);

\PawBerry\Core\Config::load($appConfig, $dbConfig);
\PawBerry\Core\ErrorHandler::register();
