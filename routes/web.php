<?php

declare(strict_types=1);

use PawBerry\Controllers\AuthController;
use PawBerry\Controllers\CalendarController;
use PawBerry\Controllers\EventController;
use PawBerry\Controllers\EventTypeController;
use PawBerry\Controllers\ManifestController;
use PawBerry\Controllers\MemberController;
use PawBerry\Controllers\NotificationController;
use PawBerry\Controllers\PageController;
use PawBerry\Core\Router;
use PawBerry\Middleware\AuthMiddleware;
use PawBerry\Middleware\CsrfMiddleware;
use PawBerry\Middleware\GuestMiddleware;

/** @var Router $router */

$auth = new AuthMiddleware();
$guest = new GuestMiddleware();
$csrf = new CsrfMiddleware();

$router->get('/manifest.webmanifest', [ManifestController::class, 'show']);

$router->get('/login', [AuthController::class, 'showLogin'], [$guest]);
$router->post('/login', [AuthController::class, 'login'], [$guest, $csrf]);
$router->post('/logout', [AuthController::class, 'logout'], [$auth, $csrf]);

$router->get('/', [PageController::class, 'dashboard'], [$auth]);
$router->get('/calendar', [CalendarController::class, 'index'], [$auth]);
$router->get('/notifications', [NotificationController::class, 'index'], [$auth]);
$router->post('/notifications/snooze-popups', [NotificationController::class, 'snoozeAllPopups'], [$auth, $csrf]);
$router->post('/notifications/{id}/snooze-popup', [NotificationController::class, 'snoozePopup'], [$auth, $csrf]);
$router->post('/notifications/{id}/disable', [NotificationController::class, 'disable'], [$auth, $csrf]);
$router->get('/settings', [PageController::class, 'settings'], [$auth]);

// Events
$router->get('/events', [EventController::class, 'index'], [$auth]);
$router->get('/events/new', [EventController::class, 'createForm'], [$auth]);
$router->post('/events', [EventController::class, 'store'], [$auth, $csrf]);
$router->get('/events/{id}/edit', [EventController::class, 'editForm'], [$auth]);
$router->post('/events/{id}/delete', [EventController::class, 'destroy'], [$auth, $csrf]);
$router->get('/events/{id}/attachments/{attachmentId}', [EventController::class, 'attachment'], [$auth]);
$router->get('/events/{id}', [EventController::class, 'show'], [$auth]);
$router->post('/events/{id}', [EventController::class, 'update'], [$auth, $csrf]);

// The Gang — members
$router->get('/gang', [MemberController::class, 'index'], [$auth]);
$router->get('/gang/new', [MemberController::class, 'createForm'], [$auth]);
$router->post('/gang', [MemberController::class, 'store'], [$auth, $csrf]);
$router->get('/gang/{id}/edit', [MemberController::class, 'editForm'], [$auth]);
$router->post('/gang/{id}/archive', [MemberController::class, 'archive'], [$auth, $csrf]);
$router->post('/gang/{id}/restore', [MemberController::class, 'restore'], [$auth, $csrf]);
$router->get('/gang/{id}/photo/{variant}', [MemberController::class, 'photo'], [$auth]);
$router->get('/gang/{id}', [MemberController::class, 'show'], [$auth]);
$router->post('/gang/{id}', [MemberController::class, 'update'], [$auth, $csrf]);

// Event types
$router->get('/event-types', [EventTypeController::class, 'index'], [$auth]);
$router->get('/event-types/new', [EventTypeController::class, 'createForm'], [$auth]);
$router->post('/event-types', [EventTypeController::class, 'store'], [$auth, $csrf]);
$router->get('/event-types/{id}/edit', [EventTypeController::class, 'editForm'], [$auth]);
$router->post('/event-types/{id}/archive', [EventTypeController::class, 'archive'], [$auth, $csrf]);
$router->post('/event-types/{id}/restore', [EventTypeController::class, 'restore'], [$auth, $csrf]);
$router->post('/event-types/{id}', [EventTypeController::class, 'update'], [$auth, $csrf]);
