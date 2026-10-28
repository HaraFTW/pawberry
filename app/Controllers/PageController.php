<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use DateTimeImmutable;
use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\View;
use PawBerry\Services\AuthService;
use PawBerry\Services\DashboardService;

final class PageController
{
    public function dashboard(Request $request): Response
    {
        $data = (new DashboardService())->build();
        $today = new DateTimeImmutable('today');

        return View::render('pages/dashboard', [
            'title' => 'Dashboard',
            'nav' => 'dashboard',
            'members' => $data['members'],
            'needsAttention' => $data['needs_attention'],
            'attentionCount' => $data['attention_count'],
            'recentEvents' => $data['recent_events'],
            'upcoming' => $data['upcoming'],
            'todayLabel' => $today->format('l, F j'),
        ]);
    }

    public function settings(Request $request): Response
    {
        $auth = new AuthService();
        $user = $auth->user();

        return View::render('pages/settings', [
            'title' => 'Settings',
            'nav' => 'settings',
            'user' => $user,
        ]);
    }
}
