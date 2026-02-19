<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('room_type', 16);
            $table->unsignedInteger('room_id')->nullable();
            $table->unsignedInteger('user_id');
            $table->text('body')->nullable();
            $table->string('media_url', 2048)->nullable();
            $table->unsignedBigInteger('reply_to_id')->nullable();
            $table->timestamps();

            $table->index(['room_type', 'room_id', 'created_at']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reply_to_id')->references('id')->on('chat_messages')->nullOnDelete();
        });

        Schema::create('chat_message_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('message_id');
            $table->unsignedInteger('user_id');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
            $table->foreign('message_id')->references('id')->on('chat_messages')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_receipts');
        Schema::dropIfExists('chat_messages');
    }
};
