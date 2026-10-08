<?php

namespace App\Http\Controllers\Api\BroadbandAccount;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BroarbandAccount\BroadbandAccountService;
use Illuminate\Http\Request;

class BroadbandAccountController extends Controller
{
    public function __construct(private BroadbandAccountService $broadbandAccountService) {}

    public function bindAccount(Request $request)
    {
        $request->validate([
            'account_number' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
        ]);

        $account = $this->broadbandAccountService->find($request->account_number, $request->customer_name);

        if (!$account) {
            return response()->json(
                [
                    'message' => 'broadband_account_not_match',
                ],
                404,
            );
        }

        $user = $request->user();

        if ($user->broadband_account_number === $request->account_number) {
            return response()->json(
                [
                    'message' => 'broadband_already_connected',
                ],
                200,
            );
        }

        if (isset($user->broadband_account_number) && $user->broadband_account_number !== $request->account_number) {
            return response()->json(
                [
                    'message' => 'broadband_different_account',
                ],
                409,
            );
        }

        $alreadyUsed = User::where('broadband_account_number', $account['account_number'])
            ->where('id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            return response()->json(
                [
                    'message' => 'broadband_account_in_use',
                ],
                409,
            );
        }

        $user->update([
            'broadband_account_number' => $account['account_number'],
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
}
