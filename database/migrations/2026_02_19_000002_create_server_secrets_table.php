<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('server_secrets', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id');
            $table->string('secret_key', 191);
            $table->longText('encrypted_value');
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->onDelete('cascade');
            $table->unique(['server_id', 'secret_key']);
            $table->index(['server_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_secrets');
    }
};
