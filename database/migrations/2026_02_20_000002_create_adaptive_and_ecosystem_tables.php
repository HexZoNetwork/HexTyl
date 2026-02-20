<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('adaptive_baselines', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('server_id')->nullable();
            $table->string('metric_key', 120);
            $table->double('ewma')->default(0);
            $table->double('variance')->default(1);
            $table->double('last_value')->default(0);
            $table->double('anomaly_score')->default(0);
            $table->unsignedInteger('sample_count')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['server_id', 'metric_key'], 'adaptive_baselines_server_metric_unique');
            $table->index(['metric_key', 'anomaly_score']);
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
        });

        Schema::create('event_bus_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 140);
            $table->string('source', 80)->nullable();
            $table->unsignedInteger('server_id')->nullable();
            $table->unsignedInteger('actor_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_key', 'created_at']);
            $table->foreign('server_id')->references('id')->on('servers')->nullOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('url', 1024);
            $table->string('event_pattern', 140)->default('*');
            $table->string('secret', 191)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->unsignedInteger('delivery_success_count')->default(0);
            $table->unsignedInteger('delivery_failed_count')->default(0);
            $table->timestamp('last_delivery_at')->nullable();
            $table->string('last_delivery_status', 32)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['enabled', 'event_pattern']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('reputation_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('indicator_type', 40); // ip|fingerprint|signature
            $table->string('indicator_value', 191);
            $table->string('source', 120)->default('local');
            $table->unsignedInteger('confidence')->default(50);
            $table->string('risk_level', 20)->default('medium');
            $table->json('meta')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['indicator_type', 'indicator_value', 'source'], 'reputation_indicators_unique');
            $table->index(['indicator_type', 'risk_level']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reputation_indicators');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('event_bus_events');
        Schema::dropIfExists('adaptive_baselines');
    }
};
