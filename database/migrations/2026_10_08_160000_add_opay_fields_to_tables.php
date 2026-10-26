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
                if (!Schema::hasColumn($tableName, 'payment_method')) {
                    $table->string('payment_method')->default('bank_transfer')->after('payment_reference');
                }
                if (!Schema::hasColumn($tableName, 'opay_reference')) {
                    $table->string('opay_reference')->nullable()->after('payment_method');
                }
                if (!Schema::hasColumn($tableName, 'opay_order_id')) {
                    $table->string('opay_order_id')->nullable()->after('opay_reference');
                }
                if (!Schema::hasColumn($tableName, 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('opay_order_id');
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
                if (Schema::hasColumn($tableName, 'payment_method')) $columns[] = 'payment_method';
                if (Schema::hasColumn($tableName, 'opay_reference')) $columns[] = 'opay_reference';
                if (Schema::hasColumn($tableName, 'opay_order_id')) $columns[] = 'opay_order_id';
                if (Schema::hasColumn($tableName, 'paid_at')) $columns[] = 'paid_at';

                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
