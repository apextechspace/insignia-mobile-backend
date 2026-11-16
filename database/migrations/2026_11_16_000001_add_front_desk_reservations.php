<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('reserved_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->whereNull('user_id')->exists()) {
            throw new RuntimeException('Assign guest accounts to accountless reservations before rolling back.');
        }

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reserved_by');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
