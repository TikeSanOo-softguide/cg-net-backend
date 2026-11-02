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

    public function bindAccount(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'account_number' => ['required', 'string', 'max:32'],
            'service_type' => ['required', 'string', 'in:FTTH,CG'],
        ]);

        $serviceType = $validated['service_type'];
        $accountNumber = $validated['account_number'];

        try {
            $account = $this->accountInfoService->find($serviceType, $accountNumber);
        } catch (ConnectionException) {
            return $this->result(false, MessageCode::BbConnectionFailed, 502);
        } catch (BroadbandApiException $exception) {
            return $this->result(false, MessageCode::BbUpstreamFailed, 502, ['message' => $exception->errorCode]);
        }

        if (!$account) {
            return $this->result(false, MessageCode::BbNotMatch, 404);
        }

        $alreadyBound = User::query()
            ->where('id', '!=', $user->id)
            ->where('broadband_account_number', $accountNumber)
            ->where('service_type', $serviceType)
            ->exists();

        if ($alreadyBound) {
            return $this->result(false, MessageCode::BbAlreadyBound, 409);
        }

        $user->update([
            'service_type' => $serviceType,
            'broadband_account_number' => $accountNumber,
        ]);

        return $this->result(true, MessageCode::BbConnected, 200);
    }

    public function unbindAccount(Request $request)
    {
        $user = $request->user();
        $serviceType = $request->input('service_type');
        $accountNumber = $request->input('account_number');

        if (
            !is_string($accountNumber) ||
            $user->broadband_account_number !== $accountNumber ||
            ($serviceType !== null && $user->service_type !== $serviceType)
        ) {
            abort(404);
        }

        $user->update([
            'service_type' => null,
            'broadband_account_number' => null,
        ]);

        return $this->result(true, MessageCode::BbDisconnected, 200);
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
