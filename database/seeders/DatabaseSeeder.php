<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Idempotent. Re-running `php artisan db:seed` will NOT overwrite an
     * existing admin account — it only creates one if it doesn't exist.
     *
     * The admin password defaults to "password" when no ADMIN_PASSWORD env
     * var is set. CHANGE IT FROM THE PROFILE PAGE AFTER FIRST LOGIN.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'sepici@gmail.com');
        $password = env('ADMIN_PASSWORD', 'password');

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => $password, // hashed via cast on save
                'role' => UserRole::Admin,
            ],
        );

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Admin account created: {$email}");
            if ($password === 'password') {
                $this->command->warn(
                    'Default password is "password". Change it immediately after first login.'
                );
            } else {
                $this->command->info('Admin password sourced from ADMIN_PASSWORD env var.');
            }
        } else {
            $this->command->info("Admin account already exists: {$email} (untouched).");
        }
    }
}
