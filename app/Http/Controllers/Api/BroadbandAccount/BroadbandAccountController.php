<?php

namespace App\Http\Controllers\Api\BroadbandAccount;

use App\Enums\MessageCode;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use App\Support\ApiMessage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadbandAccountController extends Controller
{
    public function __construct(private AccountInfoService $accountInfoService) {}

    public function checkAccount(Request $request): JsonResponse
    {
        $account = $this->validateAccount($request);

        if ($account instanceof JsonResponse) {
            return $account;
        }

        return $this->result(true, MessageCode::BbFound, 200, ['data' => $account]);
    }

    public function bindAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = $this->validateAccount($request);

        if ($account instanceof JsonResponse) {
            return $account;
        }

        $serviceType = $request->string('service_type')->toString();
        $accountNumber = $request->string('account_number')->toString();

        $user->update([
            'service_type' => $this->accountInfoService->storedServiceType($serviceType),
            'broadband_account_number' => $accountNumber,
        ]);

        return $this->result(true, MessageCode::BbConnected, 200);
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function validateAccount(Request $request): array|JsonResponse
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:32'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
        ]);

        try {
            $account = $this->accountInfoService->find(
                $validated['service_type'],
                $validated['account_number'],
            );
        } catch (ConnectionException) {
            return $this->result(false, MessageCode::BbConnectionFailed, 502);
        } catch (BroadbandApiException $exception) {
            return $this->result(false, MessageCode::BbUpstreamFailed, 502, ['message' => $exception->errorCode]);
        }

        if (! $account) {
            return $this->result(false, MessageCode::BbNotMatch, 404);
        }

        $user = $request->user();
        $alreadyBound = User::query()
            ->where('id', '!=', $user->id)
            ->where('broadband_account_number', $validated['account_number'])
            ->whereIn('service_type', array_unique([
                $validated['service_type'],
                $this->accountInfoService->storedServiceType($validated['service_type']),
            ]))
            ->exists();

        if ($alreadyBound) {
            return $this->result(false, MessageCode::BbAlreadyBound, 409);
        }

        return $account;
    }

    public function unbindAccount(Request $request)
    {
        $user = $request->user();
        $serviceType = $request->input('service_type');
        $accountNumber = $request->input('account_number');

        if (
            !is_string($accountNumber) ||
            !$this->accountMatches($user, $accountNumber, is_string($serviceType) ? $serviceType : null)
        ) {
            abort(404);
        }

        $user->update([
            'service_type' => null,
            'broadband_account_number' => null,
        ]);

        return $this->result(true, MessageCode::BbDisconnected, 200);
    }

    private function accountMatches(User $user, string $given, ?string $serviceType): bool
    {
        $stored = $user->broadband_account_number;
        if (!is_string($stored) || $stored === '') {
            return false;
        }

        $storedType = is_string($user->service_type) && $user->service_type !== ''
            ? $this->accountInfoService->providerServiceType($user->service_type)
            : null;
        $requestedType = $serviceType !== null
            ? $this->accountInfoService->providerServiceType($serviceType)
            : null;
        $type = $storedType ?? $requestedType;

        if ($storedType !== null && $requestedType !== null && $storedType !== $requestedType) {
            return false;
        }

        if ($given === $stored) {
            return true;
        }

        if (is_string($type) && $type !== '') {
            return $stored === $type . $given || $given === $type . $stored;
        }

        return false;
    }

    public function findAccount(Request $request, AccountInfoService $accountInfo)
    {
        $validated = $request->validate([
            'account_number' => ['required', 'string'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
        ]);

        try {
            $account = $accountInfo->find($validated['account_number'], $validated['service_type']);
        } catch (ConnectionException) {
            return $this->result(false, MessageCode::BbConnectionFailed, 502);
        } catch (BroadbandApiException $exception) {
            return $this->result(false, MessageCode::BbUpstreamFailed, 502, [
                'message' => $exception->errorCode,
            ]);
        }

        if (!$account) {
            return $this->result(false, MessageCode::BbNotMatch, 404);
        }

        return $this->result(true, MessageCode::BbFound, 200, [
            'data' => $account,
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function result(bool $success, MessageCode $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(ApiMessage::attach($code, array_merge(['success' => $success], $extra)), $status);
    }
}
