<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_city_ledger_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('account_reference')->unique();
            $table->string('guest_name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('guest_city_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('guest_city_ledger_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->restrictOnDelete();
            $table->enum('type', ['credit', 'debit']);
            $table->unsignedBigInteger('amount_kobo');
            $table->string('reference', 120)->unique();
            $table->string('payment_method')->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['booking_id', 'type'], 'city_ledger_one_booking_debit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_city_ledger_entries');
        Schema::dropIfExists('guest_city_ledger_accounts');
    }
};
