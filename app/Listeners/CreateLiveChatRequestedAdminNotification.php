<?php

namespace App\Listeners;

use App\Events\LiveChatRequested;
use App\Models\AdminNotification;
use App\Services\AdminNotification\AdminNotificationDelivery;

class CreateLiveChatRequestedAdminNotification
{
    public function __construct(private readonly AdminNotificationDelivery $delivery) {}

    public function handle(LiveChatRequested $event): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => 'Live Chat Requested',
            'body' => 'A customer is requesting to chat with a staff member.',
            'reference_type' => 'chat_conversation',
            'reference_id' => $event->conversation->getKey(),
        ]);

        $this->delivery->deliver($notification, 'support.view');
    }
}
