<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_transactions', fn (Blueprint $table) => $table->string('type')->change());
        Schema::table('monthly_statements', function (Blueprint $table): void {
            $table->json('snapshot')->nullable();
            $table->decimal('actual_float_balance', 12, 2)->nullable();
            $table->text('float_reconciliation_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_statements', fn (Blueprint $table) => $table->dropColumn([
            'snapshot', 'actual_float_balance', 'float_reconciliation_notes',
        ]));
    }
};
