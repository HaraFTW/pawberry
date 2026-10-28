<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Config;
use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Url;

final class ManifestController
{
    public function show(Request $request): Response
    {
        $manifest = [
            'name' => Config::appName(),
            'short_name' => 'PawBerry',
            'description' => 'A cozy personal pet care journal',
            'start_url' => Url::to('/'),
            'scope' => Config::basePath() === '' ? '/' : Config::basePath() . '/',
            'display' => 'standalone',
            'background_color' => '#342720',
            'theme_color' => '#342720',
            'icons' => [
                [
                    'src' => Url::asset('icons/icon-192.png'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => Url::asset('icons/icon-512.png'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
            ],
        ];

        return Response::json($manifest)->withHeader('Content-Type', 'application/manifest+json; charset=utf-8');
    }
}
