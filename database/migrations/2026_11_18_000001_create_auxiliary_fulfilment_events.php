<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auxiliary_fulfilment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('order_type');
            $table->unsignedBigInteger('order_id');
            $table->foreignId('handled_by')->constrained('users')->restrictOnDelete();
            $table->string('previous_status');
            $table->string('status');
            $table->timestamp('created_at');
            $table->index(['order_type', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auxiliary_fulfilment_events');
    }
};
