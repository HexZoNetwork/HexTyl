<?php

use Illuminate\Database\Migrations\Migration;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\RoleScope;

class AddTesterRole extends Migration
{
    public function up()
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Tester'],
            [
                'description' => 'Security tester role with fast account creation access.',
                'is_system_role' => true,
            ]
        );

        if (!$role->is_system_role) {
            $role->is_system_role = true;
            $role->save();
        }

        foreach (['user.read', 'user.create'] as $scope) {
            RoleScope::query()->firstOrCreate([
                'role_id' => $role->id,
                'scope' => $scope,
            ]);
        }
    }

    public function down()
    {
        $role = Role::query()->whereRaw('LOWER(name) = ?', ['tester'])->first();
        if (!$role) {
            return;
        }

        RoleScope::query()->where('role_id', $role->id)->whereIn('scope', ['user.read', 'user.create'])->delete();

        if ($role->users()->count() === 0) {
            $role->delete();
        }
    }
}
