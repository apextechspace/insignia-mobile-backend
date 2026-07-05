<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guest_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // The resident registering the guest
            $table->string('guest_name');
            $table->string('guest_phone')->nullable();
            $table->string('relationship')->nullable(); // e.g. Friend, Family, Client
            $table->date('visit_date')->nullable();
            $table->time('expected_arrival')->nullable();
            $table->string('purpose')->nullable(); // e.g. Visit, Business
            $table->enum('status', ['pending', 'approved', 'denied', 'completed'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_registrations');
    }
};
