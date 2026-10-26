<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add operational status tracking columns to the rooms table.
     * This keeps the original is_available flag intact for the mobile API
     * and adds a more granular status field for the operations panel.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            // Operational status for the Operations Panel (distinct from is_available on the mobile API)
            $table->enum('status', ['available', 'occupied', 'cleaning', 'maintenance', 'reserved'])
                  ->default('available')
                  ->after('is_available');

            // Free-text notes for housekeeping handovers
            $table->text('housekeeping_notes')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn(['status', 'housekeeping_notes']);
        });
    }
};
