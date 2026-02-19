<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRolesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->increments('id'); // ID 1 = Root (System), 2 = Admin (System), 3 = User (System)
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_system_role')->default(false); // Protects critical roles from deletion
            $table->timestamps();
        });

        // Seed initial roles
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'Root', 'description' => 'System Root', 'is_system_role' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Admin', 'description' => 'Administrator', 'is_system_role' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'User', 'description' => 'Standard User', 'is_system_role' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
}
