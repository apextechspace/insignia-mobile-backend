<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payment_policy', function (Blueprint $table) {
            $table->string('deposit_mode', 20)->default('percentage');
            $table->boolean('cancellation_terms_enabled')->default(false);
            $table->boolean('late_booking_grace_enabled')->default(false);
        });
        Schema::table('bookings', fn (Blueprint $table) => $table->json('reservation_terms')->nullable());
        Schema::table('rooms', fn (Blueprint $table) => $table->boolean('show_vat_included')->default(false));
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('bookings')->whereNotNull('reservation_terms')->exists()) {
            throw new RuntimeException('Preserve agreed reservation terms before rolling back this migration.');
        }
        Schema::table('bookings', fn (Blueprint $table) => $table->dropColumn('reservation_terms'));
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn('show_vat_included'));
        Schema::table('reservation_payment_policy', fn (Blueprint $table) => $table->dropColumn(['deposit_mode', 'cancellation_terms_enabled', 'late_booking_grace_enabled']));
    }
};
