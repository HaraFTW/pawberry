<?php

declare(strict_types=1);

namespace PawBerry\Services;

use PawBerry\Repositories\EventRepository;
use PawBerry\Repositories\MemberRepository;
use PawBerry\Repositories\NotificationRepository;

final class DashboardService
{
    private MemberRepository $members;
    private EventRepository $events;
    private NotificationRepository $notifications;

    public function __construct(
        ?MemberRepository $members = null,
        ?EventRepository $events = null,
        ?NotificationRepository $notifications = null
    ) {
        $this->members = $members ?? new MemberRepository();
        $this->events = $events ?? new EventRepository();
        $this->notifications = $notifications ?? new NotificationRepository();
    }

    /**
     * @return array{
     *   members: list<array<string, mixed>>,
     *   needs_attention: list<array<string, mixed>>,
     *   attention_count: int,
     *   recent_events: list<array<string, mixed>>,
     *   upcoming: list<array<string, mixed>>
     * }
     */
    public function build(): array
    {
        $needsAttention = $this->notifications->listAttention();
        $upcoming = $this->notifications->listUpcoming();

        return [
            'members' => $this->members->allActive(),
            'needs_attention' => $needsAttention,
            'attention_count' => count($needsAttention),
            'recent_events' => $this->events->recent(5),
            'upcoming' => array_slice($upcoming, 0, 3),
        ];
    }
}
