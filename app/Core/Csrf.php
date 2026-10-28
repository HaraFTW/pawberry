<?php

declare(strict_types=1);

namespace PawBerry\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function validate(?string $token): bool
    {
        Session::start();
        $expected = Session::get(self::SESSION_KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || $token === '') {
            return false;
        }
        return hash_equals($expected, $token);
    }

    public static function validateRequest(Request $request): bool
    {
        $token = $request->input('_csrf');
        if (!is_string($token) || $token === '') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        return self::validate(is_string($token) ? $token : null);
    }
}
