<?php

namespace App\Http\Controllers\Api\BroadbandAccount;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BroarbandAccount\AccountInfoService;
use App\Services\BroarbandAccount\BroadbandApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;

class BroadbandAccountController extends Controller
{
    public function __construct(private AccountInfoService $accountInfoService) {}

    public function bindAccount(Request $request)
    {
        $request->validate([
            'account_number' => ['required', 'string'],
            'service_type' => ['required', 'string'],
        ]);

        try {
            $account = $this->accountInfoService->find($request->account_number, $request->service_type);
        } catch (ConnectionException) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_api_connection_failed',
                ],
                502,
            );
        } catch (BroadbandApiException $exception) {
            return response()->json(
                [
                    'success' => false,
                    'message' => $exception->errorCode,
                ],
                502,
            );
        }

        if (!$account) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_account_not_match',
                ],
                404,
            );
        }

        $user = $request->user();
        $broadbandAccount = $request->service_type . $request->account_number;

        if ($user->broadband_account_number === $broadbandAccount) {
            return response()->json(
                [
                    'success' => true,
                    'message' => 'broadband_already_connected',
                ],
                200,
            );
        }

        if (isset($user->broadband_account_number) && $user->broadband_account_number !== $broadbandAccount) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_different_account',
                ],
                409,
            );
        }

        $alreadyUsed = User::where('broadband_account_number', $broadbandAccount)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_account_in_use',
                ],
                409,
            );
        }

        $user->update([
            'broadband_account_number' => $broadbandAccount,
        ]);

        return response()->json(
            [
                'success' => true,
                'message' => 'Successfully connected',
            ],
            200,
        );
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

        return response()->json(
            [
                'success' => true,
                'message' => 'Successfully disconnected',
            ],
            200,
        );
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
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_api_connection_failed',
                ],
                502,
            );
        } catch (BroadbandApiException $exception) {
            return response()->json(
                [
                    'success' => false,
                    'message' => $exception->errorCode,
                ],
                502,
            );
        }

        if (!$account) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'broadband_account_not_match',
                ],
                404,
            );
        }

        return response()->json(
            [
                'success' => true,
                'message' => 'Account found successfully',
                'data' => $account,
            ],
            200,
        );
    }
}
