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
        Schema::table('services', function (Blueprint $table) {
            $table->enum('type', ['booking', 'reservation', 'request'])->default('booking')->after('category');
            $table->boolean('requires_variant')->default(false)->after('is_active');
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->foreignId('service_variant_id')->nullable()->after('service_id')->constrained()->onDelete('set null');
            $table->date('date')->nullable()->after('scheduled_at');
            $table->time('time')->nullable()->after('date');
            $table->json('metadata')->nullable()->after('receipt_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['type', 'requires_variant']);
        });

        Schema::table('service_bookings', function (Blueprint $table) {
            $table->dropForeign(['service_variant_id']);
            $table->dropColumn(['service_variant_id', 'date', 'time', 'metadata']);
        });
    }
};
