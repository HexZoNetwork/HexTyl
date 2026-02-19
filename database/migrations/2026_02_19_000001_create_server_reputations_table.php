<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('server_reputations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->unique();
            $table->unsignedTinyInteger('stability_score')->default(50);
            $table->unsignedTinyInteger('uptime_score')->default(50);
            $table->unsignedTinyInteger('abuse_score')->default(50);
            $table->unsignedTinyInteger('trust_score')->default(50);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->index('trust_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_reputations');
    }
};
