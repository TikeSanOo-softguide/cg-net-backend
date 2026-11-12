<?php

namespace App\Listeners;

use App\Events\FinancialSummaryFinished;
use App\Models\AdminNotification;
use App\Services\AdminNotification\AdminNotificationDelivery;

class CreateFinancialSummaryNotification
{
    public function __construct(private readonly AdminNotificationDelivery $delivery) {}

    public function handle(FinancialSummaryFinished $event): void
    {
        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => $event->completed ? 'Financial Summary Completed' : 'Financial Summary Failed',
            'body' => $event->completed
                ? 'The financial summary report completed successfully.'
                : 'The financial summary report failed. Please review the report logs for details.',
            'severity' => $event->completed ? 'normal' : 'alert',
            'reference_type' => $event->completed ? 'financial_summary_snapshot' : null,
            'reference_id' => $event->completed ? $event->snapshotId : null,
        ]);

        $this->delivery->deliver($notification, 'reports.view');
    }
}
