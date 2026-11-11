<?php

namespace App\Http\Controllers\Api\InternetBill;

use App\Enums\LedgerTransactionType;
use App\Enums\MessageCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InternetBill\PayInternetBillRequest;
use App\Http\Resources\InternetBillPaymentHistoryDetailResource;
use App\Http\Resources\InternetBillPaymentHistoryResource;
use App\Models\LedgerTransaction;
use App\Models\User;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use App\Services\InternetBill\InternetBillPaymentService;
use App\Support\ApiMessage;
use App\Support\CustomerPassword;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;

class InternetBillController extends Controller
{
    public function __construct(
        protected InternetBillPaymentService $payments,
        protected AccountInfoService $accountInfo,
    ) {}

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:32'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
        ]);

        $accountNumber = trim($validated['account_number']);
        $serviceType = $validated['service_type'];

        try {
            $account = $this->accountInfo->find($serviceType, $accountNumber);
        } catch (ConnectionException|BroadbandApiException) {
            return response()->json(
                ApiMessage::attach(MessageCode::FtthBillingFailed, ['success' => false]),
                502,
            );
        }

        if ($account === null) {
            return response()->json(
                ApiMessage::attach(MessageCode::FtthAccountMissing, ['success' => false]),
                404,
            );
        }

        $productId = $account['productID'] ?? null;
        $productPrice = $account['productPrice'] ?? null;

        if (
            (! is_string($productId) && ! is_int($productId)) ||
            trim((string) $productId) === '' ||
            ! is_numeric($productPrice) ||
            (float) $productPrice < 1
        ) {
            return response()->json(
                ApiMessage::attach(MessageCode::FtthBillingFailed, ['success' => false]),
                502,
            );
        }

        $price = round((float) $productPrice, 2);

        return response()->json(ApiMessage::attach(MessageCode::FtthAccountChecked, [
            'success' => true,
            'data' => [
                'account' => $accountNumber,
                'service_type' => $serviceType,
                'product_id' => (string) $productId,
                'product_name' => $this->accountText($account, ['productName']),
                'product_price' => $price,
                'product_speed' => $this->accountText($account, ['productSpeed']),
                'customer_name' => $this->accountText($account, ['name', 'customerName', 'customer_name']),
                'phone' => $this->accountText($account, ['mobile', 'phone']),
                'address' => $this->accountText($account, ['address']),
                'outstanding_amount' => $price,
            ],
        ]));
    }

    public function pay(PayInternetBillRequest $request): JsonResponse
    {
        $validated = $request->validated();
        /** @var User $user */
        $user = $request->user();

        CustomerPassword::verify($user, (string) $validated['password']);

        $result = $this->payments->pay(
            user: $user,
            accountNumber: (string) $validated['broadband_account_number'],
            serviceType: (string) $validated['service_type'],
            idempotencyKey: (string) $validated['idempotency_key'],
            remark: isset($validated['remark']) ? (string) $validated['remark'] : null,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json($result['body'], $result['http_status']);
    }

    public function history(Request $request): AnonymousResourceCollection|JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $accountNumber = $this->boundAccountNumber($request);

        if ($accountNumber === null) {
            return $this->missingAccountResponse();
        }

        $payments = $this->historyQuery($accountNumber)
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate($validated['per_page'] ?? 20);

        return InternetBillPaymentHistoryResource::collection($payments);
    }

    public function show(Request $request, int $id): InternetBillPaymentHistoryDetailResource|JsonResponse
    {
        $accountNumber = $this->boundAccountNumber($request);

        if ($accountNumber === null) {
            return $this->missingAccountResponse();
        }

        $transaction = $this->historyQuery($accountNumber)
            ->whereHas('billPayment', fn (Builder $query) => $query->whereKey($id))
            ->first();

        if ($transaction === null) {
            return response()->json(
                ApiMessage::attach(MessageCode::SysNotFound, ['success' => false]),
                404,
            );
        }

        return new InternetBillPaymentHistoryDetailResource($transaction);
    }

    private function boundAccountNumber(Request $request): ?string
    {
        $accountNumber = trim((string) $request->user()->broadband_account_number);

        return $accountNumber === '' ? null : $accountNumber;
    }

    private function missingAccountResponse(): JsonResponse
    {
        return response()->json(
            ApiMessage::attach(MessageCode::FtthAccountMissing, ['success' => false]),
            422,
        );
    }

    /**
     * @return Builder<LedgerTransaction>
     */
    private function historyQuery(string $accountNumber): Builder
    {
        return LedgerTransaction::query()
            ->whereIn('type', LedgerTransactionType::internetBillValues())
            ->whereHas('billPayment', fn (Builder $query) => $query->where(
                'broadband_account_number',
                $accountNumber,
            ))
            ->with('billPayment');
    }

    /**
     * @param  array<string, mixed>  $account
     * @param  list<string>  $keys
     */
    private function accountText(array $account, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $account[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    public function pendingSlip(Request $request): JsonResponse
    {
        $result = $this->payments->pendingSlip($request->user());

        return response()->json($result['body'], $result['http_status']);
    }

    public function paidSlips(Request $request): JsonResponse
    {
        $result = $this->payments->paidSlips($request->user());

        return response()->json($result['body'], $result['http_status']);
    }
}
