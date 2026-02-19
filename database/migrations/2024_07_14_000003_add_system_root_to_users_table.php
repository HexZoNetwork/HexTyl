<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSystemRootToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_system_root')->default(false)->after('root_admin');
            $table->integer('role_id')->unsigned()->nullable()->after('is_system_root');
            
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('set null');
        });

        // Migrate existing root admins
        // We will assign them ID 2 (Admin) for now, and set ID 1 user as is_system_root = true if they exist.
         DB::transaction(function () {
            // Set User ID 1 as Immutable Root
            DB::table('users')->where('id', 1)->update(['is_system_root' => true, 'role_id' => 1]);

            // Migrate other root_admin = 1 users to Admin role (ID 2)
            DB::table('users')->where('root_admin', 1)->where('id', '!=', 1)->update(['role_id' => 2]);
            
            // Migrate valid users (root_admin = 0) to User role (ID 3)
            DB::table('users')->where('root_admin', 0)->update(['role_id' => 3]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
            $table->dropColumn('is_system_root');
        });
    }
}
