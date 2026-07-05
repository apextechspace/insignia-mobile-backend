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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_reference')->nullable()->after('status');
            $table->string('receipt_url')->nullable()->after('payment_reference');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_reference')->nullable()->after('status');
            $table->string('receipt_url')->nullable()->after('payment_reference');
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->string('payment_reference')->nullable()->after('status');
            $table->string('receipt_url')->nullable()->after('payment_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'receipt_url']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'receipt_url']);
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropColumn(['payment_reference', 'receipt_url']);
        });
    }
};
