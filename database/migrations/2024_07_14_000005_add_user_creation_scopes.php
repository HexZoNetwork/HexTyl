<?php

use Illuminate\Database\Migrations\Migration;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\RoleScope;

class AddUserCreationScopes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Find Admin Role (assuming ID 2 or name 'Admin')
        $adminRole = Role::where('name', 'Admin')->first();

        if ($adminRole) {
            // Admin can create users
            RoleScope::firstOrCreate([
                'role_id' => $adminRole->id,
                'scope' => 'user.create',
            ]);
            
            // Admin can list users
            RoleScope::firstOrCreate([
                'role_id' => $adminRole->id,
                'scope' => 'user.read',
            ]);
            
            // Admin can update users (but not Root, blocked by code)
            RoleScope::firstOrCreate([
                'role_id' => $adminRole->id,
                'scope' => 'user.update',
            ]);
        }
        
        // Root role (if exists as DB entry) gets everything logic-wise, 
        // but if we want to be explicit we could add '*', but `User::hasScope` handles root bypass.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Optional cleanup
    }
}
