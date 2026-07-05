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
        // Modify the enum to include 'order'
        DB::statement("ALTER TABLE services MODIFY COLUMN type ENUM('booking', 'reservation', 'request', 'order') DEFAULT 'booking'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back (this might fail if there are rows with 'order', so we should ideally update them first)
        DB::statement("ALTER TABLE services MODIFY COLUMN type ENUM('booking', 'reservation', 'request') DEFAULT 'booking'");
    }
};
