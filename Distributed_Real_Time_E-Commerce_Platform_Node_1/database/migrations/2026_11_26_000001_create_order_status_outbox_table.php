<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->json('payload');
            $table->timestamp('recorded_at');

            $table->unique(['order_id', 'to_status']);
            $table->index(['order_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_outbox');
    }
};
