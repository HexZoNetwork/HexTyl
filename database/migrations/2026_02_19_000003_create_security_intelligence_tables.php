<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('event_type', 120);
            $table->string('risk_level', 20)->default('info');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_type', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });

        Schema::create('risk_snapshots', function (Blueprint $table) {
            $table->increments('id');
            $table->string('identifier', 191)->unique();
            $table->unsignedInteger('risk_score')->default(0);
            $table->string('risk_mode', 20)->default('normal');
            $table->string('geo_country', 10)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('server_health_scores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id')->unique();
            $table->unsignedInteger('stability_index')->default(100);
            $table->unsignedInteger('crash_penalty')->default(0);
            $table->unsignedInteger('restart_penalty')->default(0);
            $table->unsignedInteger('snapshot_penalty')->default(0);
            $table->string('last_reason', 255)->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
        });

        Schema::create('node_health_scores', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('node_id')->unique();
            $table->unsignedInteger('health_score')->default(100);
            $table->unsignedInteger('reliability_rating')->default(100);
            $table->unsignedInteger('crash_frequency')->default(0);
            $table->unsignedInteger('placement_score')->default(100);
            $table->text('migration_recommendation')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });

        Schema::create('secret_vault_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('server_id');
            $table->string('secret_key', 191);
            $table->unsignedInteger('version')->default(1);
            $table->text('encrypted_value');
            $table->timestamp('rotates_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'secret_key', 'version'], 'svv_server_key_version_unique');
            $table->index(['server_id', 'secret_key']);
            $table->foreign('server_id')->references('id')->on('servers')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secret_vault_versions');
        Schema::dropIfExists('node_health_scores');
        Schema::dropIfExists('server_health_scores');
        Schema::dropIfExists('risk_snapshots');
        Schema::dropIfExists('security_events');
    }
};
