<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('held_messages', function (Blueprint $table) {
            $table->id();
            $table->string('topic');                          // e.g. notification.email
            $table->json('payload');                          // the original message body
            $table->string('status', 20)->default('pending'); // pending | published
            $table->unsignedInteger('attempts')->default(0);  // relay attempts so far
            $table->text('last_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);                  // relay reads pending rows oldest first
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('held_messages');
    }
};
