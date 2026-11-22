<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('external_source', 40)->nullable();
            $table->string('external_reference', 120)->nullable();
            $table->string('external_unit_reference', 40)->nullable();
            $table->json('external_details')->nullable();
            $table->unique(['external_source', 'external_reference', 'external_unit_reference'], 'bookings_external_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_external_unit_unique');
            $table->dropColumn(['external_source', 'external_reference', 'external_unit_reference', 'external_details']);
        });
    }
};
