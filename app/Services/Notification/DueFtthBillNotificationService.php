<?php

namespace App\Services\Notification;

use App\Enums\UserStatus;
use App\Jobs\SendFtthBillDueNotificationJob;
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
        $today = CarbonImmutable::today();
        $lastDueDate = $today->addDays(7);
        $bills = $this->billing->lookupBillsDueBetween($today->toDateString(), $lastDueDate->toDateString());
        $billsByAccount = [];

        foreach ($bills as $bill) {
            $accountNumber = $bill['account_number'] ?? ($bill['broadband_account_number'] ?? null);

            if (!is_string($accountNumber) || trim($accountNumber) === '') {
                Log::warning('Billing server returned a due bill without a broadband account number.');
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
     * @param  array<string, mixed>  $bill
     */
    private function dueDate(array $bill): CarbonImmutable
    {
        $value = $bill['due_date'] ?? null;

        if (!is_string($value) || trim($value) === '') {
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
}
