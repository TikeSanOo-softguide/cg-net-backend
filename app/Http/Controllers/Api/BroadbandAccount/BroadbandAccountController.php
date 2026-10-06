<?php

namespace App\Http\Controllers\Api\BroadbandAccount;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BroarbandAccount\BroadbandAccountService;
use Illuminate\Http\Request;

class BroadbandAccountController extends Controller
{
    public function __construct(private BroadbandAccountService $broadbandAccountService) {}

    public function connect(Request $request)
    {
        $request->validate([
            'account_number' => ['required', 'string'],
            'customer_name' => ['required', 'string'],
        ]);

        $account = $this->broadbandAccountService->find($request->account_number, $request->customer_name);

        if (!$account) {
            return response()->json(
                [
                    'message' => 'Account number and customer name do not match.',
                ],
                404,
            );
        }

        $user = $request->user();

        if ($user->broadband_account_number === $request->account_number) {
            return response()->json(
                [
                    'message' => 'Already connected to your account.',
                ],
                200,
            );
        }

        if ($user->broadband_account_number !== null) {
            return response()->json(
                [
                    'message' => 'Your account is already connected to a broadband account.',
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
                    'message' => 'This broadband account is already connected to another user.',
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
}
