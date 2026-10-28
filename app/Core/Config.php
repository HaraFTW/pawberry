<?php

declare(strict_types=1);

namespace PawBerry\Core;

final class Config
{
    /** @var array<string, mixed> */
    private static array $app = [];

    /** @var array<string, mixed> */
    private static array $database = [];

    /** @param array<string, mixed> $app */
    /** @param array<string, mixed> $database */
    public static function load(array $app, array $database): void
    {
        self::$app = $app;
        self::$database = $database;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$app;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /** @return array<string, mixed> */
    public static function database(): array
    {
        return self::$database;
    }

    public static function env(): string
    {
        return (string) self::get('env', 'production');
    }

    public static function isProduction(): bool
    {
        return self::env() === 'production';
    }

    public static function isDevelopment(): bool
    {
        return self::env() === 'development';
    }

    /**
     * Normalized base path: empty string for root, or "/pawberry" (no trailing slash).
     */
    public static function basePath(): string
    {
        $path = (string) self::get('base_path', '/');
        $path = '/' . trim($path, '/');

        return $path === '/' ? '' : $path;
    }

    public static function baseUrl(): string
    {
        return rtrim((string) self::get('base_url', ''), '/');
    }

    public static function appName(): string
    {
        return (string) self::get('name', 'PawBerry');
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (($_SERVER['SERVER_PORT'] ?? null) === '443') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string) $forwarded) === 'https';
    }
}
