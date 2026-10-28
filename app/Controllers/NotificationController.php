<?php

declare(strict_types=1);

namespace PawBerry\Controllers;

use PawBerry\Core\Request;
use PawBerry\Core\Response;
use PawBerry\Core\Session;
use PawBerry\Core\Url;
use PawBerry\Core\View;
use PawBerry\Repositories\NotificationRepository;
use PawBerry\Support\NotificationPresenter;

final class NotificationController
{
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationRepository();
    }

    public function index(Request $request): Response
    {
        $view = strtolower((string) $request->query('view', 'active'));
        if (!in_array($view, ['active', 'upcoming', 'history'], true)) {
            $view = 'active';
        }

        $attentionCount = $this->notifications->countAttention();
        $upcomingCount = $this->notifications->countUpcoming();

        $items = match ($view) {
            'upcoming' => $this->notifications->listUpcoming(),
            'history' => $this->notifications->listHistory(),
            default => $this->notifications->listAttention(),
        };

        return View::render('notifications/index', [
            'title' => 'Notifications',
            'nav' => 'notifications',
            'view' => $view,
            'items' => $items,
            'attentionCount' => $attentionCount,
            'upcomingCount' => $upcomingCount,
        ]);
    }

    public function disable(Request $request): Response
    {
        $id = (int) $request->param('id', 0);
        $notification = $id > 0 ? $this->notifications->find($id) : null;
        if ($notification === null) {
            return View::error(404, 'Notification not found', 'That reminder could not be found.');
        }

        if ((string) $notification['status'] !== 'active') {
            Session::flash('info', 'That reminder is no longer active.');
            return Response::redirect(Url::to('/notifications'));
        }

        if (!$this->notifications->disable($id)) {
            Session::flash('error', 'The reminder could not be disabled. Please try again.');
            return Response::redirect(Url::to('/notifications'));
        }

        Session::flash('success', 'Reminder disabled. It will stay in history.');
        return Response::redirect(Url::to('/notifications?view=history'));
    }

    /** Snooze one notification popup until the next calendar day. */
    public function snoozePopup(Request $request): Response
    {
        $id = (int) $request->param('id', 0);
        $notification = $id > 0 ? $this->notifications->find($id) : null;
        if ($notification === null) {
            return Response::json(['ok' => false, 'error' => 'Notification not found.'], 404);
        }

        if ((string) $notification['status'] !== 'active') {
            return Response::json(['ok' => false, 'error' => 'That reminder is no longer active.'], 409);
        }

        $through = NotificationPresenter::todayString();
        if (!$this->notifications->snoozePopupThrough($id, $through)) {
            return Response::json(['ok' => false, 'error' => 'Could not snooze that reminder. Please try again.'], 500);
        }

        return Response::json([
            'ok' => true,
            'id' => $id,
            'snoozed_through' => $through,
        ]);
    }

    /** Snooze every currently popup-eligible notification until the next calendar day. */
    public function snoozeAllPopups(Request $request): Response
    {
        $through = NotificationPresenter::todayString();
        $count = $this->notifications->snoozeAllPopupEligible($through);

        return Response::json([
            'ok' => true,
            'snoozed_through' => $through,
            'count' => $count,
        ]);
    }
}
