<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('stock_deducted')->default(false);
        });
        // The previous checkout API deducted inventory when the order was created.
        // Preserve that accounting for existing orders so settlement never deducts twice.
        DB::table('orders')->update(['stock_deducted' => true]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('stock_deducted'));
    }
};
