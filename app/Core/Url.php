<?php

declare(strict_types=1);

namespace PawBerry\Core;

final class Url
{
    /**
     * Generate an application-relative URL that respects the configured base path.
     */
    public static function to(string $path = '/'): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $base = Config::basePath();

        if ($path === '/') {
            return $base === '' ? '/' : $base . '/';
        }

        return $base . $path;
    }

    public static function absolute(string $path = '/'): string
    {
        return Config::baseUrl() . self::to($path);
    }

    public static function asset(string $path): string
    {
        return self::to('/assets/' . ltrim($path, '/'));
    }
}
