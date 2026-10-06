<?php

namespace App\Listeners;

use App\Events\AdminNotificationCreated;
use App\Events\ServiceRequestSubmitted;
use App\Models\AdminNotification;
use InvalidArgumentException;

class CreateAdminNotification
{
    // Keys are ServiceRequestSubmitted types; add the title/message for every supported request type.
    /**
     * @var array<string, array{title: string, message: string}>
     */
    private const REQUEST_NOTIFICATIONS = [
        'installation_application' => [
            'title' => 'New Broadband Application',
            'message' => 'A new broadband application has been submitted.',
        ],
        'change_password_request' => [
            'title' => 'New Change Password Request',
            'message' => 'A new change password request has been submitted.',
        ],
        'change_plan_request' => [
            'title' => 'New Change Plan Request',
            'message' => 'A new change plan request has been submitted.',
        ],
        'failure_report' => [
            'title' => 'New Failure Report',
            'message' => 'A new failure report has been submitted.',
        ],
        'relocation_request' => [
            'title' => 'New Relocation Request',
            'message' => 'A new relocation request has been submitted.',
        ],
    ];

    public function handle(ServiceRequestSubmitted $event): void
    {
        $content = self::REQUEST_NOTIFICATIONS[$event->type]
            ?? throw new InvalidArgumentException("Unsupported service request notification type [{$event->type}].");

        // "type" is the broad notification category; "reference_type" and "reference_id"
        // retain the exact request kind and saved record used for filtering and navigation.
        $notification = AdminNotification::query()->create([
            'type' => 'request',
            'title' => $content['title'],
            'body' => $content['message'],
            'reference_type' => $event->type,
            'reference_id' => $event->requestId,
        ]);

        AdminNotificationCreated::dispatch($notification);
    }
}
