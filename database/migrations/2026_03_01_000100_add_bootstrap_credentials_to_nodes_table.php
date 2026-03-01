<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->string('bootstrap_host', 255)->nullable()->after('maintenance_mode');
            $table->unsignedInteger('bootstrap_port')->nullable()->after('bootstrap_host');
            $table->string('bootstrap_username', 64)->nullable()->after('bootstrap_port');
            $table->string('bootstrap_auth_type', 16)->nullable()->after('bootstrap_username');
            $table->text('bootstrap_password')->nullable()->after('bootstrap_auth_type');
            $table->longText('bootstrap_private_key')->nullable()->after('bootstrap_password');
            $table->boolean('bootstrap_strict_host_key')->default(false)->after('bootstrap_private_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn([
                'bootstrap_host',
                'bootstrap_port',
                'bootstrap_username',
                'bootstrap_auth_type',
                'bootstrap_password',
                'bootstrap_private_key',
                'bootstrap_strict_host_key',
            ]);
        });
    }
};
