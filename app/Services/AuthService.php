<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Core\Config;
use PawBerry\Core\Csrf;
use PawBerry\Core\Database;
use PawBerry\Core\Request;
use PawBerry\Core\Session;

final class AuthService
{
    private const SESSION_USER_ID = 'user_id';
    private const SESSION_AUTH_VERSION = 'auth_version';
    private const SETTINGS_AUTH_VERSION = 'auth_version';

    public function check(): bool
    {
        Session::start();
        if ($this->userId() !== null) {
            if ($this->sessionAuthVersionValid()) {
                return true;
            }
            // Credentials were rotated (e.g. create-user.php) — drop this session.
            Session::destroy();
        }

        return $this->attemptRememberLogin();
    }

    public function userId(): ?int
    {
        Session::start();
        $id = Session::get(self::SESSION_USER_ID);
        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /** @return array<string, mixed>|null */
    public function user(): ?array
    {
        $id = $this->userId();
        if ($id === null) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT id, display_name, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user === false ? null : $user;
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    public function attemptLogin(string $passphrase, bool $remember, Request $request): array
    {
        $rateLimiter = new RateLimiter();
        if ($rateLimiter->tooManyAttempts($request->ip())) {
            return ['ok' => false, 'error' => 'Too many attempts. Please wait a bit and try again.'];
        }

        $stmt = Database::connection()->query('SELECT id, passphrase_hash, display_name FROM users ORDER BY id ASC LIMIT 1');
        $user = $stmt->fetch();

        if ($user === false || !password_verify($passphrase, (string) $user['passphrase_hash'])) {
            $rateLimiter->hit($request->ip());
            return ['ok' => false, 'error' => 'Invalid passphrase. Please try again.'];
        }

        if (password_needs_rehash((string) $user['passphrase_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($passphrase, PASSWORD_DEFAULT);
            $update = Database::connection()->prepare('UPDATE users SET passphrase_hash = ?, updated_at = NOW() WHERE id = ?');
            $update->execute([$newHash, $user['id']]);
        }

        $rateLimiter->clear($request->ip());
        Session::regenerate(true);
        Session::set(self::SESSION_USER_ID, (int) $user['id']);
        Session::set(self::SESSION_AUTH_VERSION, $this->currentAuthVersion());

        // Refresh CSRF after regenerate
        Csrf::token();

        if ($remember) {
            $this->issueRememberToken((int) $user['id'], $request);
        } else {
            $this->clearRememberCookie();
        }

        return ['ok' => true];
    }

    public function logout(Request $request): void
    {
        $cookieName = (string) Config::get('remember.cookie', 'pawberry_remember');
        $raw = $request->cookie($cookieName);
        if (is_string($raw) && $raw !== '') {
            $this->revokeRememberToken($raw);
        }

        $this->clearRememberCookie();
        Session::destroy();
    }

    private function attemptRememberLogin(): bool
    {
        $cookieName = (string) Config::get('remember.cookie', 'pawberry_remember');
        $raw = $_COOKIE[$cookieName] ?? null;
        if (!is_string($raw) || $raw === '' || !str_contains($raw, ':')) {
            return false;
        }

        [$selector, $validator] = explode(':', $raw, 2);
        if ($selector === '' || $validator === '' || !ctype_xdigit($selector)) {
            $this->clearRememberCookie();
            return false;
        }

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT id, user_id, token_hash, expires_at FROM remember_tokens
             WHERE selector = ? LIMIT 1'
        );
        $stmt->execute([$selector]);
        $row = $stmt->fetch();

        if ($row === false) {
            $this->clearRememberCookie();
            return false;
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            $this->deleteTokenById((int) $row['id']);
            $this->clearRememberCookie();
            return false;
        }

        $hash = hash('sha256', $validator);
        if (!hash_equals((string) $row['token_hash'], $hash)) {
            // Possible theft — revoke this selector
            $this->deleteTokenById((int) $row['id']);
            $this->clearRememberCookie();
            return false;
        }

        Session::regenerate(true);
        Session::set(self::SESSION_USER_ID, (int) $row['user_id']);
        Session::set(self::SESSION_AUTH_VERSION, $this->currentAuthVersion());
        Csrf::token();

        // Rotate token
        $this->deleteTokenById((int) $row['id']);
        $this->issueRememberToken((int) $row['user_id'], Request::fromGlobals());

        return true;
    }

    private function issueRememberToken(int $userId, Request $request): void
    {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $days = (int) Config::get('remember.lifetime_days', 365);
        $expiresAt = (new \DateTimeImmutable('+' . $days . ' days'))->format('Y-m-d H:i:s');

        $stmt = Database::connection()->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at, user_agent, ip_address, created_at, last_used_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $userId,
            $selector,
            $hash,
            $expiresAt,
            mb_substr($request->userAgent(), 0, 255),
            $request->ip(),
        ]);

        $this->setRememberCookie($selector . ':' . $validator, $days);
    }

    private function revokeRememberToken(string $raw): void
    {
        if (!str_contains($raw, ':')) {
            return;
        }
        [$selector] = explode(':', $raw, 2);
        if ($selector === '') {
            return;
        }
        $stmt = Database::connection()->prepare('DELETE FROM remember_tokens WHERE selector = ?');
        $stmt->execute([$selector]);
    }

    private function deleteTokenById(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $stmt->execute([$id]);
    }

    private function setRememberCookie(string $value, int $days): void
    {
        $name = (string) Config::get('remember.cookie', 'pawberry_remember');
        $httponly = (bool) Config::get('remember.httponly', true);
        $samesite = (string) Config::get('remember.samesite', 'Lax');
        $secureCfg = Config::get('remember.secure');
        $secure = is_bool($secureCfg) ? $secureCfg : Config::isHttps();
        $path = Config::basePath() === '' ? '/' : Config::basePath() . '/';

        setcookie($name, $value, [
            'expires' => time() + ($days * 86400),
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite,
        ]);
    }

    private function clearRememberCookie(): void
    {
        $name = (string) Config::get('remember.cookie', 'pawberry_remember');
        $path = Config::basePath() === '' ? '/' : Config::basePath() . '/';
        $secureCfg = Config::get('remember.secure');
        $secure = is_bool($secureCfg) ? $secureCfg : Config::isHttps();

        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $path,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function revokeAllRememberTokens(?int $userId = null): void
    {
        $userId ??= $this->userId();
        if ($userId === null) {
            return;
        }
        $stmt = Database::connection()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Revoke remember-me tokens and bump the auth version so existing sessions fail check().
     */
    public function invalidateAllCredentials(int $userId): void
    {
        $this->revokeAllRememberTokens($userId);
        $this->bumpAuthVersion();
    }

    public function currentAuthVersion(): int
    {
        $settings = new SettingsService();
        $value = $settings->get(self::SETTINGS_AUTH_VERSION, '1');

        return max(1, (int) $value);
    }

    public function bumpAuthVersion(): int
    {
        $next = $this->currentAuthVersion() + 1;
        (new SettingsService())->set(self::SETTINGS_AUTH_VERSION, (string) $next);

        return $next;
    }

    private function sessionAuthVersionValid(): bool
    {
        $sessionVersion = Session::get(self::SESSION_AUTH_VERSION);
        if (!is_int($sessionVersion) && !is_numeric($sessionVersion)) {
            return false;
        }

        return (int) $sessionVersion === $this->currentAuthVersion();
    }
}

