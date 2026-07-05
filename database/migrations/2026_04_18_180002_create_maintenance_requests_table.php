<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category')->default('General'); // AC, Plumbing, Electrical, General
            $table->string('title');
            $table->text('description');
            $table->enum('priority', ['low', 'normal', 'urgent'])->default('normal');
            $table->enum('status', ['open', 'in-progress', 'resolved', 'closed'])->default('open');
            $table->text('admin_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
