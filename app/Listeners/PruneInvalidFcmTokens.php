<?php

namespace App\Listeners;

use App\Models\DeviceToken;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\SendReport;
use NotificationChannels\Fcm\FcmChannel;

class PruneInvalidFcmTokens
{
    public function handle(NotificationFailed $event): void
    {
        if ($event->channel !== FcmChannel::class) {
            return;
        }

        $report = $event->data['report'] ?? null;

        if (! $report instanceof SendReport) {
            return;
        }

        Log::warning('FCM delivery failed.', [
            'token' => substr($report->target()->value(), 0, 16).'…',
            'error' => $report->error()?->getMessage(),
            'error_type' => $report->error() ? $report->error()::class : null,
        ]);

        if ($report->messageWasSentToUnknownToken() || $report->messageTargetWasInvalid()) {
            DeviceToken::query()->where('token', $report->target()->value())->delete();
        }
    }
}
