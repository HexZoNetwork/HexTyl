<?php

namespace Pterodactyl\Console\Commands;

use Pterodactyl\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class MakeRootUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'root {--password= : Custom root password (must match policy)} {--length=20 : Generated password length (minimum 12)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or reset the Root User (ID 0) with full privileges.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Creating/Resetting Root User (ID 0)...');

        if (!Schema::hasTable('users')) {
            $this->error('Users table does not exist. Run migrations first.');
            return 1;
        }

        try {
            $user = User::find(0);
            if ($user) {
                if ($this->confirm('Root user already exists. Overwrite?')) {
                    DB::table('users')->where('id', 0)->delete();
                } else {
                    $this->info('Operation cancelled.');
                    return 0;
                }
            }

            $passwordInput = (string) ($this->option('password') ?? '');
            $length = max(12, (int) $this->option('length'));
            $plainPassword = $passwordInput !== '' ? $passwordInput : $this->generateRootPassword($length);

            if (!$this->isValidRootPassword($plainPassword)) {
                $this->error('Invalid password policy. Use minimum 12 chars and only [a-zA-Z1-9-=_].');

                return 1;
            }

            // Prepare columns based on schema availability to avoid SQL errors
            $columns = [
                'id' => 0,
                'uuid' => (string) Uuid::uuid4(),
                'email' => 'root@hexzo.com',
                'password' => Hash::make($plainPassword),
                'root_admin' => 1,
                'language' => 'en',
                'use_totp' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // Conditionally add columns
            if (Schema::hasColumn('users', 'username')) $columns['username'] = 'root';
            if (Schema::hasColumn('users', 'name_first')) $columns['name_first'] = 'System';
            if (Schema::hasColumn('users', 'name_last')) $columns['name_last'] = 'Root';
            if (Schema::hasColumn('users', 'is_system_root')) $columns['is_system_root'] = 1;
            if (Schema::hasColumn('users', 'gravatar')) $columns['gravatar'] = 1;

            DB::table('users')->insert($columns);

            $this->info('Root User created successfully.');
            $this->table(['Field', 'Value'], [
                ['ID', 0],
                ['Email', 'root@hexzo.com'],
                ['Password', $plainPassword],
                ['Role', 'Root Admin']
            ]);
            $this->warn('Save this password now. It is not stored in plaintext.');

            return 0;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }

    private function isValidRootPassword(string $password): bool
    {
        if (strlen($password) < 12) {
            return false;
        }

        return (bool) preg_match('/^[a-zA-Z1-9=_-]+$/', $password);
    }

    private function generateRootPassword(int $length): string
    {
        $length = max(12, $length);
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ123456789-=_';
        $max = strlen($alphabet) - 1;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}
