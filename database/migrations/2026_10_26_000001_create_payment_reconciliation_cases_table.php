<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_reconciliation_cases', function (Blueprint $table) {
            $table->id();
            $table->string('gateway');
            $table->string('payment_reference')->unique();
            $table->string('provider_transaction_id')->nullable();
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('reason');
            $table->string('status')->default('needs_review');
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_cases');
    }
};
