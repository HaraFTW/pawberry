<?php

declare(strict_types=1);

namespace PawBerry\Middleware;

use PawBerry\Core\Csrf;
use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;

final class CsrfMiddleware
{
    public function __invoke(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if (!Csrf::validateRequest($request)) {
                if ($request->isAjax()) {
                    return Response::json(['error' => 'Invalid security token. Please refresh and try again.'], 419);
                }

                Session::flash('error', 'Your session expired. Please try again.');
                $referer = $_SERVER['HTTP_REFERER'] ?? null;
                if (is_string($referer) && $referer !== '' && str_starts_with($referer, \PawBerry\Core\Config::baseUrl())) {
                    return Response::redirect($referer);
                }

                return Response::redirect(\PawBerry\Core\Url::to('/'));
            }
        }

        return $next($request);
    }
}
