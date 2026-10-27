<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('manual_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name', 120);
            $table->string('account_number', 10);
            $table->string('account_name', 160);
            $table->boolean('is_active')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_bank_accounts');
    }
};