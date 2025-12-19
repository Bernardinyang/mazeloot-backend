<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Create a super admin user
        User::factory()->create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin@example.com',
            'role' => UserRoleEnum::SUPER_ADMIN,
        ]);

        // Create a regular user
        User::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'role' => UserRoleEnum::USER,
        ]);

        // Seed user statuses (must be before users)
        $this->call([
            UserStatusSeeder::class,
        ]);

        // Seed cover layouts
        $this->call([
            CoverStylesSeeder::class,
        ]);
    }
}
