<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite stores enum values as text for tests. The raw MODIFY
        // statement below is supported only by MySQL/MariaDB.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Modify the enum to include 'order'
        DB::statement("ALTER TABLE services MODIFY COLUMN type ENUM('booking', 'reservation', 'request', 'order') DEFAULT 'booking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Revert back (this might fail if there are rows with 'order', so we should ideally update them first)
        DB::statement("ALTER TABLE services MODIFY COLUMN type ENUM('booking', 'reservation', 'request') DEFAULT 'booking'");
    }
};
