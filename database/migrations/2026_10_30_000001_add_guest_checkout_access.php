<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_access_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('token_hash', 64);
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('hold_expires_at')->nullable()->index();
            $table->unsignedTinyInteger('guests')->default(1);
            $table->text('special_requests')->nullable();
            $table->json('booking_contact')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['hold_expires_at']);
            $table->dropColumn(['hold_expires_at', 'guests', 'special_requests', 'booking_contact']);
        });
        Schema::dropIfExists('guest_access_requests');
    }
};
