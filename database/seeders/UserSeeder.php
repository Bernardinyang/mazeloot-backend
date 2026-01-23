<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin
        User::firstOrCreate(
            ['email' => 'super@mazeloot.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Password'),
                'role' => UserRoleEnum::SUPER_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        // Create admin
        User::firstOrCreate(
            ['email' => 'admin@mazeloot.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('Password'),
                'role' => UserRoleEnum::ADMIN,
                'email_verified_at' => now(),
            ]
        );
    }
}
