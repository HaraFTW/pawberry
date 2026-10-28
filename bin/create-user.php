<?php

declare(strict_types=1);

/**
 * Create or update the single application user.
 * Usage: php bin/create-user.php
 *        php bin/create-user.php "Display Name"
 *
 * Always maintains at most one user. Re-running updates that user.
 * Updating the passphrase revokes all remember-me tokens and invalidates
 * existing PHP sessions (auth version bump).
 *
 * Passphrases are never printed or logged.
 */

require dirname(__DIR__) . '/bootstrap.php';

use PawBerry\Core\Database;
use PawBerry\Services\AuthService;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This command must be run from the CLI.\n");
    exit(1);
}

$displayName = $argv[1] ?? 'Keeper';

echo "PawBerry — create / update user\n";
echo "Display name: {$displayName}\n";

$passphrase = readSecret('Enter passphrase: ');
if ($passphrase === '') {
    fwrite(STDERR, "Passphrase cannot be empty.\n");
    exit(1);
}

$confirm = readSecret('Confirm passphrase: ');
if (!hash_equals($passphrase, $confirm)) {
    $passphrase = '';
    $confirm = '';
    fwrite(STDERR, "Passphrases do not match.\n");
    exit(1);
}

if (strlen($passphrase) < 8) {
    $passphrase = '';
    $confirm = '';
    fwrite(STDERR, "Passphrase should be at least 8 characters.\n");
    exit(1);
}

$hash = password_hash($passphrase, PASSWORD_DEFAULT);
$passphrase = '';
$confirm = '';

if ($hash === false) {
    fwrite(STDERR, "Failed to hash passphrase.\n");
    exit(1);
}

$pdo = Database::connection();
$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$existing = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();

if ($userCount > 1) {
    fwrite(STDERR, "Warning: multiple users found; only the lowest id will be updated.\n");
}

$auth = new AuthService();

if ($existing === false) {
    $stmt = $pdo->prepare(
        'INSERT INTO users (display_name, passphrase_hash, created_at, updated_at)
         VALUES (?, ?, NOW(), NOW())'
    );
    $stmt->execute([$displayName, $hash]);
    $userId = (int) $pdo->lastInsertId();
    $auth->invalidateAllCredentials($userId);
    unset($hash);
    echo "User created (id {$userId}).\n";
} else {
    $userId = (int) $existing['id'];
    $stmt = $pdo->prepare(
        'UPDATE users SET display_name = ?, passphrase_hash = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt->execute([$displayName, $hash, $userId]);
    unset($hash);
    $auth->invalidateAllCredentials($userId);
    echo "User updated (id {$userId}).\n";
    echo "Existing sessions and remember-me tokens were invalidated.\n";
}

echo "You can now log in at the application URL.\n";

/**
 * Read a secret from STDIN. Does not echo the value back.
 */
function readSecret(string $prompt): string
{
    echo $prompt;

    if (DIRECTORY_SEPARATOR === '\\') {
        // Windows CLI cannot reliably hide input without extensions; read from STDIN only.
        $line = fgets(STDIN);
        return $line === false ? '' : trim($line);
    }

    system('stty -echo');
    $line = fgets(STDIN);
    system('stty echo');
    echo PHP_EOL;
    return $line === false ? '' : trim($line);
}
