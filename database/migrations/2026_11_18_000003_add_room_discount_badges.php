<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->boolean('discount_enabled')->default(false);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->string('discount_label', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rooms', fn (Blueprint $table) => $table->dropColumn(['discount_enabled', 'discount_percentage', 'discount_label']));
    }
};
