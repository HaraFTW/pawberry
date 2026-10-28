<?php

declare(strict_types=1);

namespace PawBerry\Middleware;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Url;
use PawBerry\Services\AuthService;

final class GuestMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        $auth = new AuthService();
        if ($auth->check()) {
            return Response::redirect(Url::to('/'));
        }

        return $next($request);
    }
}
