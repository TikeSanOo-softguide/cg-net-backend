<?php

namespace App\Listeners;

use App\Events\LedgerHealthScanFinished;
use App\Models\AdminNotification;
use App\Services\AdminNotification\AdminNotificationDelivery;

class CreateLedgerHealthNotification
{
    public function __construct(private readonly AdminNotificationDelivery $delivery) {}

    public function handle(LedgerHealthScanFinished $event): void
    {
        $scanType = ucfirst($event->type->value);
        $notification = AdminNotification::query()->create([
            'type' => 'other',
            'title' => $event->completed ? 'Ledger Health Check Completed' : 'Ledger Health Check Failed',
            'body' => $event->completed
                ? ($event->hasDiscrepancies
                    ? "The {$scanType} ledger health check completed with discrepancies."
                    : "The {$scanType} ledger health check completed successfully.")
                : "The {$scanType} ledger health check failed. Please review the scan logs for details.",
            'severity' => ! $event->completed || $event->hasDiscrepancies ? 'alert' : 'normal',
            'reference_type' => $event->completed ? 'ledger_health_snapshot_'.$event->type->value : null,
            'reference_id' => $event->completed ? $event->snapshotId : null,
        ]);

        $this->delivery->deliver($notification, 'reports.view');
    }
}
