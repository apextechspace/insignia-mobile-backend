<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tables = ['bookings', 'orders', 'food_orders', 'service_bookings'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'flutterwave_reference')) {
                    $table->string('flutterwave_reference')->nullable()->after('payment_method');
                }
                if (!Schema::hasColumn($tableName, 'flutterwave_transaction_id')) {
                    $table->string('flutterwave_transaction_id')->nullable()->after('flutterwave_reference');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = ['bookings', 'orders', 'food_orders', 'service_bookings'];

        foreach ($tables as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $columns = [];
                if (Schema::hasColumn($tableName, 'flutterwave_reference')) $columns[] = 'flutterwave_reference';
                if (Schema::hasColumn($tableName, 'flutterwave_transaction_id')) $columns[] = 'flutterwave_transaction_id';

                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
