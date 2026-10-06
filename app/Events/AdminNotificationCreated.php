<?php

namespace App\Events;

use App\Models\AdminNotification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AdminNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly AdminNotification $notification) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.notifications')];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification.created';
    }

    /**
     * @return array{id: int, type: string, title: string, message: string, reference_type: string|null, reference_id: int|null, read_at: string|null, created_at: string, category: string, href: string|null}
     */
    public function broadcastWith(): array
    {
        // Broadcast the saved notification in the same shape used by the admin notification dropdown.
        return $this->notification->toDropdownArray();
    }
}
