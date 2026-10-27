<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users');
            $table->string('bank_transaction_reference')->unique();
            $table->decimal('verified_amount', 12, 2);
            $table->string('decision'); // approved or rejected
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['booking_id', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_payment_reviews');
    }
};
