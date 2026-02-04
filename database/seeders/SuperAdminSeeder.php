<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'bernodelimited@gmail.com'],
            [
                'first_name' => 'Bernardin',
                'last_name' => 'Yang',
                'password' => Hash::make('Password'),
                'role' => UserRoleEnum::SUPER_ADMIN,
                'email_verified_at' => now(),
            ]
        );
    }
}
