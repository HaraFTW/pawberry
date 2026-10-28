<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return View::render('auth/login', [
            'title' => 'Welcome back',
            'error' => Session::flash('error'),
            'oldRemember' => false,
        ], 'layouts/guest');
    }

    public function login(Request $request): Response
    {
        $passphrase = trim((string) $request->input('passphrase', ''));
        $remember = (string) $request->input('remember', '') === '1';

        if ($passphrase === '') {
            return View::render('auth/login', [
                'title' => 'Welcome back',
                'error' => 'Please enter your passphrase.',
                'oldRemember' => $remember,
            ], 'layouts/guest');
        }

        $auth = new AuthService();
        $result = $auth->attemptLogin($passphrase, $remember, $request);

        if (!$result['ok']) {
            return View::render('auth/login', [
                'title' => 'Welcome back',
                'error' => $result['error'] ?? 'Invalid passphrase. Please try again.',
                'oldRemember' => $remember,
            ], 'layouts/guest');
        }

        return Response::redirect(Url::to('/'));
    }

    public function logout(Request $request): Response
    {
        $auth = new AuthService();
        $auth->logout($request);
        return Response::redirect(Url::to('/login'));
    }
}
