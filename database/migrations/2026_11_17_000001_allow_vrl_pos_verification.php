<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payment_policy', fn (Blueprint $table) =>
            $table->boolean('vrl_can_verify_pos')->default(false));
    }

    public function down(): void
    {
        Schema::table('reservation_payment_policy', fn (Blueprint $table) =>
            $table->dropColumn('vrl_can_verify_pos'));
    }
};
