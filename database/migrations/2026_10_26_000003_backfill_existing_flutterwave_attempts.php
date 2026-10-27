<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $types = [
            'bookings' => \App\Models\Booking::class,
            'orders' => \App\Models\Order::class,
            'food_orders' => \App\Models\FoodOrder::class,
            'service_bookings' => \App\Models\ServiceBooking::class,
        ];

        foreach ($types as $table => $model) {
            DB::table($table)->whereNotNull('flutterwave_reference')
                ->where('flutterwave_reference', '<>', '')
                ->orderBy('id')->chunkById(250, function ($rows) use ($model, $table) {
                    foreach ($rows as $row) {
                        $amount = $table === 'food_orders' ? $row->total_amount : $row->total_price;
                        $paid = !empty($row->paid_at)
                            && $row->payment_method === 'flutterwave'
                            && !empty($row->flutterwave_transaction_id);

                        // Keep legacy paid claims separate from unverified attempts.
                        $status = $paid ? 'confirmed' : (
                            !empty($row->paid_at) ? 'needs_review' : 'initiated'
                        );

                        DB::table('payment_attempts')->insertOrIgnore([
                            'gateway' => 'flutterwave',
                            'reference' => $row->flutterwave_reference,
                            'payable_type' => $model,
                            'payable_id' => $row->id,
                            'amount' => $amount,
                            'currency' => 'NGN',
                            'status' => $status,
                            'provider_transaction_id' => $row->flutterwave_transaction_id ?: null,
                            'confirmed_at' => $paid ? $row->paid_at : null,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => now(),
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        // Do not erase payment evidence when rolling back application code.
    }
};
