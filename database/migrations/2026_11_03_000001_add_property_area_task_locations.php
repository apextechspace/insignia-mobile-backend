<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->foreignId('property_area_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
        });
        foreach (['Upstairs Foyer', 'Reception', 'Downstairs Foyer', 'Inverter Room', 'Outdoor', 'Boys’ Quarters'] as $name) {
            DB::table('property_areas')->insert(['name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_area_id');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn('verified_at');
        });
        Schema::dropIfExists('property_areas');
    }
};
