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
        $request->validate([
            'account_number' => ['required', 'string', 'max:25'],
            'service_type' => ['required', 'string'],
        ]);

        try {
            $account = $this->accountInfoService->find($request->account_number, $request->service_type);
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

        $user = $request->user();
        $broadbandAccount = $request->service_type . $request->account_number;

        if ($user->broadband_account_number === $broadbandAccount) {
            return $this->result(true, MessageCode::BbAlreadyConnected, 200);
        }

        if (isset($user->broadband_account_number) && $user->broadband_account_number !== $broadbandAccount) {
            return $this->result(false, MessageCode::BbDifferentAccount, 409);
        }

        $alreadyUsed = User::where('broadband_account_number', $broadbandAccount)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return $this->result(false, MessageCode::BbInUse, 409);
        }

        $user->update([
            'broadband_account_number' => $broadbandAccount,
        ]);

        return $this->result(true, MessageCode::BbConnected, 200);
    }

    public function unbindAccount(Request $request)
    {
        $user = $request->user();
        $accountNumber = $request->account_number;

        if ($accountNumber === null) {
            abort(404);
        }

        $user->update([
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
