<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ManualBankAccount;
use Illuminate\Http\JsonResponse;

class ManualBankAccountController extends Controller
{
    public function show(): JsonResponse
    {
        $account = ManualBankAccount::current();
        $ready = ManualBankAccount::isReady();

        return response()->json([
            'is_configured' => $ready,
            'bank_name' => $ready ? $account->bank_name : null,
            'account_number' => $ready ? $account->account_number : null,
            'account_name' => $ready ? $account->account_name : null,
        ])->header('Cache-Control', 'no-store, private');
    }
}