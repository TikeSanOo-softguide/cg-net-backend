<?php

namespace App\Services\Notification;

use App\Enums\NotificationActionType;
use App\Enums\UserStatus;
use App\Jobs\SendInternetBillDueNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Billing\BillingServerClient;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;

class DueInternetBillNotificationService
{
    public function __construct(private readonly BillingServerClient $billing) {}

    public function dispatchDueBills(): int
    {
        $dueAccounts = $this->dueAccountEntries();
        $accountNumbers = collect($dueAccounts)->pluck('account_number')->all();

        $dispatched = 0;

        foreach (array_chunk($accountNumbers, 100) as $accountNumberChunk) {
            User::query()
                ->where('status', UserStatus::Active)
                ->whereIn('broadband_account_number', $accountNumberChunk)
                ->orderBy('id')
                ->chunkById(100, function ($users) use ($dueAccounts, &$dispatched): void {
                    $actionIds = $users
                        ->map(
                            fn(User $user): string => $dueAccounts[
                                'account:' . (string) $user->broadband_account_number
                            ]['action_id'],
                        )
                        ->unique()
                        ->values();
                    $sentNotifications = Notification::query()
                        ->whereIn('user_id', $users->modelKeys())
                        ->where('action_type', NotificationActionType::FtthBill->value)
                        ->whereIn('action_id', $actionIds)
                        ->whereNotNull('sent_at')
                        ->get(['user_id', 'action_id'])
                        ->mapWithKeys(function (Notification $notification): array {
                            return [
                                $notification->user_id . ':' . $notification->action_id => true,
                            ];
                        });

                    foreach ($users as $user) {
                        $accountNumber = (string) $user->broadband_account_number;
                        $dueAccount = $dueAccounts['account:' . $accountNumber];

                        if ($sentNotifications->has($user->id . ':' . $dueAccount['action_id'])) {
                            continue;
                        }

                        SendInternetBillDueNotificationJob::dispatch(
                            userId: (int) $user->id,
                            accountNumber: $accountNumber,
                            endTime: $dueAccount['endtime'],
                            actionId: $dueAccount['action_id'],
                        );
                        $dispatched++;
                    }
                });
        }

        return $dispatched;
    }

    /**
     * @return list<array{
     *     user_id: int,
     *     customer_name: string,
     *     account_number: string,
     *     endtime: string,
     *     action_id: string,
     *     notification_id: int|null,
     *     state: 'not_recorded'|'unsent'|'sent',
     *     has_device: bool
     * }>
     */
    public function dueBillAlerts(?string $accountNumber = null): array
    {
        $dueAccounts = $this->dueAccountEntries($accountNumber);

        if ($dueAccounts === []) {
            return [];
        }

        $accountNumbers = collect($dueAccounts)->pluck('account_number')->values();
        $actionIds = collect($dueAccounts)->pluck('action_id')->values();
        $users = User::query()
            ->where('status', UserStatus::Active)
            ->whereIn('broadband_account_number', $accountNumbers)
            ->withCount('deviceTokens')
            ->orderBy('id')
            ->get(['id', 'name', 'broadband_account_number']);

        if ($users->isEmpty()) {
            return [];
        }

        $notifications = Notification::query()
            ->whereIn('user_id', $users->modelKeys())
            ->where('action_type', NotificationActionType::FtthBill->value)
            ->whereIn('action_id', $actionIds)
            ->get(['id', 'user_id', 'action_id', 'sent_at'])
            ->keyBy(fn(Notification $notification): string => $notification->user_id . ':' . $notification->action_id);

        $alerts = [];

        foreach ($users as $user) {
            $accountNumber = (string) $user->broadband_account_number;
            $dueAccount = $dueAccounts['account:' . $accountNumber];
            $notification = $notifications->get($user->id . ':' . $dueAccount['action_id']);
            $alerts[] = [
                'user_id' => (int) $user->id,
                'customer_name' => $user->name,
                'account_number' => $accountNumber,
                'endtime' => $dueAccount['endtime'],
                'action_id' => $dueAccount['action_id'],
                'notification_id' => $notification?->id,
                'state' =>
                    $notification === null ? 'not_recorded' : ($notification->sent_at === null ? 'unsent' : 'sent'),
                'has_device' => $user->device_tokens_count > 0,
            ];
        }

        return $alerts;
    }

    public function dispatchBillToUser(int $userId, string $accountNumber, string $endTime, string $actionId): bool
    {
        $parsedEndTime = $this->parseEndTime($endTime);
        $normalizedEndTime = $parsedEndTime->format('Y-m-d H:i');
        if ($actionId !== $this->actionId($accountNumber, $normalizedEndTime)) {
            return false;
        }

        $user = User::query()
            ->whereKey($userId)
            ->where('status', UserStatus::Active)
            ->where('broadband_account_number', $accountNumber)
            ->first();

        if ($user === null) {
            return false;
        }

        $dueAccount = $this->dueAccountEntries($accountNumber)['account:' . $accountNumber] ?? null;
        if ($dueAccount === null || $dueAccount['endtime'] !== $normalizedEndTime) {
            return false;
        }

        if (
            Notification::query()
                ->where('user_id', $userId)
                ->where('action_type', NotificationActionType::FtthBill->value)
                ->where('action_id', $actionId)
                ->whereNotNull('sent_at')
                ->exists()
        ) {
            return false;
        }

        SendInternetBillDueNotificationJob::dispatch(
            userId: (int) $user->id,
            accountNumber: $accountNumber,
            endTime: $normalizedEndTime,
            actionId: $actionId,
        );

        return true;
    }

    /**
     * @return array<string, array{
     *     account_number: string,
     *     endtime: string,
     *     action_id: string
     * }>
     */
    private function dueAccountEntries(?string $accountFilter = null): array
    {
        $entriesByAccount = [];

        foreach ($this->billing->lookupDueAccountList() as $dueAccount) {
            $accountNumber = $dueAccount['account'];
            if ($accountFilter !== null && $accountNumber !== $accountFilter) {
                continue;
            }

            $parsedEndTime = $this->parseEndTime($dueAccount['endtime']);
            $endTime = $parsedEndTime->format('Y-m-d H:i');

            $key = 'account:' . $accountNumber;
            $existing = $entriesByAccount[$key] ?? null;
            if ($existing !== null) {
                if ($existing['endtime'] !== $endTime) {
                    throw new RuntimeException('The CG API returned multiple due times for one account.');
                }

                continue;
            }
            $entriesByAccount[$key] = [
                'account_number' => $accountNumber,
                'endtime' => $endTime,
                'action_id' => $this->actionId($accountNumber, $endTime),
            ];
        }

        return $entriesByAccount;
    }

    private function parseEndTime(string $endTime): CarbonImmutable
    {
        $parseException = null;

        foreach (['Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!' . $format, $endTime);
            } catch (InvalidArgumentException $exception) {
                $parseException = $exception;
                continue;
            }

            if ($parsed !== false && $parsed->format($format) === $endTime) {
                return $parsed;
            }
        }

        throw new RuntimeException(
            'The CG API returned an invalid due account endtime.',
            previous: $parseException,
        );
    }

    private function actionId(string $accountNumber, string $endTime): string
    {
        return $this->boundedActionId("bill_due:{$accountNumber}:{$endTime}");
    }

    private function boundedActionId(string $actionId): string
    {
        return strlen($actionId) <= 191 ? $actionId : hash('sha256', $actionId);
    }
}
