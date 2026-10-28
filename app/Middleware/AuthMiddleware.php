<?php

declare(strict_types=1);

namespace PawBerry\Middleware;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Url;
use PawBerry\Services\AuthService;

final class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $auth = new AuthService();
        if (!$auth->check()) {
            if ($request->isAjax()) {
                return Response::json(['error' => 'Unauthenticated'], 401);
            }
            return Response::redirect(Url::to('/login'));
        }

        return $next($request);
    }
}
