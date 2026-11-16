<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payment_policy', function (Blueprint $table): void {
            $table->id();
            $table->boolean('deposits_enabled')->default(false);
            $table->decimal('minimum_percent', 5, 2)->default(50);
        });
        DB::table('reservation_payment_policy')->insert(['id' => 1]);

        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('minimum_payment_kobo')->nullable();
            $table->foreignId('deposit_exception_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('deposit_exception_reason')->nullable();
            $table->timestamp('deposit_exception_at')->nullable();
        });

        Schema::create('assisted_reservation_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('amount_kobo');
            $table->string('reference', 120);
            $table->date('transaction_date');
            $table->string('proof_path');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (DB::table('assisted_reservation_payments')->exists()) {
            throw new RuntimeException('Retain assisted payment records before rolling back.');
        }
        Schema::dropIfExists('assisted_reservation_payments');
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('deposit_exception_by');
            $table->dropColumn(['minimum_payment_kobo', 'deposit_exception_reason', 'deposit_exception_at']);
        });
        Schema::dropIfExists('reservation_payment_policy');
    }
};
