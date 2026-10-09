<?php

namespace App\Services\Notification;

use App\Enums\NotificationActionType;
use App\Enums\UserStatus;
use App\Jobs\SendFtthBillDueNotificationJob;
use App\Models\Notification;
use App\Models\User;
use App\Services\Billing\BillingServerClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DueFtthBillNotificationService
{
    public function __construct(private readonly BillingServerClient $billing) {}

    public function dispatchDueBills(): int
    {
        $billsByAccount = $this->dueBillsByAccount();

        $dispatched = 0;

        foreach (array_chunk(array_keys($billsByAccount), 500) as $accountNumbers) {
            User::query()
                ->where('status', UserStatus::Active)
                ->whereIn('broadband_account_number', $accountNumbers)
                ->orderBy('id')
                ->chunkById(200, function ($users) use ($billsByAccount, &$dispatched): void {
                    foreach ($users as $user) {
                        $accountNumber = (string) $user->broadband_account_number;

                        foreach ($billsByAccount[$accountNumber] as $bill) {
                            SendFtthBillDueNotificationJob::dispatch(
                                userId: (int) $user->id,
                                accountNumber: $accountNumber,
                                dueDate: $bill['due_date'],
                                actionId: $bill['action_id'],
                            );
                            $dispatched++;
                        }
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
     *     due_date: string,
     *     action_id: string,
     *     notification_id: int|null,
     *     state: 'not_recorded'|'unsent'|'sent',
     *     has_device: bool
     * }>
     */
    public function dueBillAlerts(?string $accountNumber = null, ?string $dueDate = null): array
    {
        $billsByAccount = $this->dueBillsByAccount($accountNumber, $dueDate);

        if ($billsByAccount === []) {
            return [];
        }

        $users = User::query()
            ->where('status', UserStatus::Active)
            ->whereIn('broadband_account_number', array_keys($billsByAccount))
            ->withCount('deviceTokens')
            ->orderBy('id')
            ->get(['id', 'name', 'broadband_account_number']);

        if ($users->isEmpty()) {
            return [];
        }

        $actionIds = collect($billsByAccount)->flatten(1)->pluck('action_id')->unique()->values();
        $notifications = Notification::query()
            ->whereIn('user_id', $users->modelKeys())
            ->where('action_type', NotificationActionType::FtthBill->value)
            ->whereIn('action_id', $actionIds)
            ->get(['id', 'user_id', 'action_id', 'sent_at'])
            ->keyBy(fn (Notification $notification): string => $notification->user_id.':'.$notification->action_id);

        $alerts = [];

        foreach ($users as $user) {
            $accountNumber = (string) $user->broadband_account_number;

            foreach ($billsByAccount[$accountNumber] ?? [] as $bill) {
                $notification = $notifications->get($user->id.':'.$bill['action_id']);
                $alerts[] = [
                    'user_id' => (int) $user->id,
                    'customer_name' => $user->name,
                    'account_number' => $accountNumber,
                    'due_date' => $bill['due_date'],
                    'action_id' => $bill['action_id'],
                    'notification_id' => $notification?->id,
                    'state' => $notification === null ? 'not_recorded' : ($notification->sent_at === null ? 'unsent' : 'sent'),
                    'has_device' => $user->device_tokens_count > 0,
                ];
            }
        }

        return $alerts;
    }

    /**
     * @return list<array{due_date: string, action_id: string}>
     */
    public function dueBillsForAccount(string $accountNumber, ?string $dueDate = null): array
    {
        return $this->dueBillsByAccount($accountNumber, $dueDate)[$accountNumber] ?? [];
    }

    public function dispatchBillToUser(int $userId, string $accountNumber, string $dueDate, string $actionId): bool
    {
        $user = User::query()
            ->whereKey($userId)
            ->where('status', UserStatus::Active)
            ->where('broadband_account_number', $accountNumber)
            ->first();

        if ($user === null) {
            return false;
        }

        $billExists = collect($this->dueBillsForAccount($accountNumber, $dueDate))->contains(
            fn (array $bill): bool => $bill['due_date'] === $dueDate && $bill['action_id'] === $actionId,
        );

        if (! $billExists) {
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

        SendFtthBillDueNotificationJob::dispatch(
            userId: (int) $user->id,
            accountNumber: $accountNumber,
            dueDate: $dueDate,
            actionId: $actionId,
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function dueDate(array $bill): CarbonImmutable
    {
        $value = $bill['due_date'] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException('The billing server did not return a bill due date.');
        }

        try {
            return CarbonImmutable::parse($value)->startOfDay();
        } catch (Throwable $exception) {
            throw new RuntimeException('The billing server returned an invalid bill due date.', previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $bill
     */
    private function actionId(array $bill, string $accountNumber): string
    {
        foreach (['bill_id', 'id', 'invoice_no'] as $key) {
            $value = $bill[$key] ?? null;

            if (is_string($value) || is_int($value)) {
                return $this->boundedActionId("{$accountNumber}:{$value}");
            }
        }

        $billMonthValue = $bill['bill_month'] ?? null;
        $billMonth = is_string($billMonthValue) || is_int($billMonthValue) ? (string) $billMonthValue : 'pending';

        return $this->boundedActionId("{$accountNumber}:{$billMonth}");
    }

    private function boundedActionId(string $actionId): string
    {
        return strlen($actionId) <= 191 ? $actionId : hash('sha256', $actionId);
    }

    /**
     * @return array<string, list<array{due_date: string, action_id: string}>>
     */
    private function dueBillsByAccount(?string $accountFilter = null, ?string $dueDateFilter = null): array
    {
        $today = $dueDateFilter !== null ? CarbonImmutable::parse($dueDateFilter)->startOfDay() : CarbonImmutable::today();
        $lastDueDate = $dueDateFilter !== null ? $today : $today->addDays(7);
        $bills = $this->billing->lookupBillsDueBetween($today->toDateString(), $lastDueDate->toDateString());
        $billsByAccount = [];

        foreach ($bills as $bill) {
            $accountNumber = $bill['account_number'] ?? ($bill['broadband_account_number'] ?? null);

            if (! is_string($accountNumber) || trim($accountNumber) === '') {
                Log::warning('Billing server returned a due bill without a broadband account number.');

                continue;
            }

            if ($accountFilter !== null && $accountNumber !== $accountFilter) {
                continue;
            }

            try {
                $dueDate = $this->dueDate($bill);
            } catch (RuntimeException $exception) {
                Log::warning('Billing server returned an invalid FTTH bill due date.', [
                    'account_number' => $accountNumber,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            if ($dueDate->lt($today) || $dueDate->gt($lastDueDate)) {
                continue;
            }

            $billsByAccount[$accountNumber][] = [
                'due_date' => $dueDate->toDateString(),
                'action_id' => $this->actionId($bill, $accountNumber),
            ];
        }

        return $billsByAccount;
    }
}
