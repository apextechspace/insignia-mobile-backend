<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManualBankAccount;
use Illuminate\Http\JsonResponse;

class ManualBankAccountController extends Controller
{
    public function show(\Illuminate\Http\Request $request): JsonResponse
    {
        $validated = $request->validate(['order_type' => 'sometimes|in:booking,order,food_order,service_booking']);
        $account = ManualBankAccount::current();
        $ready = ($validated['order_type'] ?? 'booking') === 'booking' && ManualBankAccount::isReady();

        return response()->json([
            'is_configured' => $ready,
            'bank_name' => $ready ? $account->bank_name : null,
            'account_number' => $ready ? $account->account_number : null,
            'account_name' => $ready ? $account->account_name : null,
        ])->header('Cache-Control', 'no-store, private');
    }
}
