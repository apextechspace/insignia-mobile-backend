<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assisted_reservation_payments', fn (Blueprint $table) =>
            $table->string('method', 20)->default('pos'));
    }

    public function down(): void
    {
        Schema::table('assisted_reservation_payments', fn (Blueprint $table) =>
            $table->dropColumn('method'));
    }
};
