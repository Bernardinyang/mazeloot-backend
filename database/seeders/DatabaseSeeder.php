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

        // Create a super admin user (if doesn't exist)
        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => UserRoleEnum::SUPER_ADMIN,
            ]
        );

        // Create a regular user (if doesn't exist)
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'role' => UserRoleEnum::USER,
            ]
        );

        // Seed user statuses (must be before users)
        $this->call([
            UserStatusSeeder::class,
        ]);

        // Seed cover layouts
        $this->call([
            CoverStylesSeeder::class,
        ]);

        // Seed social media platforms
        $this->call([
            SocialMediaPlatformSeeder::class,
        ]);

        // Seed email notification types
        $this->call([
            EmailNotificationTypeSeeder::class,
        ]);

        // Seed products
        $this->call([
            ProductSeeder::class,
        ]);

        // Seed BYO pricing
        $this->call([
            MemoraByoPricingSeeder::class,
        ]);

        // Seed fixed pricing tiers
        $this->call([
            MemoraPricingTiersSeeder::class,
        ]);

        // Seed super admin
        $this->call([
            SuperAdminSeeder::class,
        ]);

        // Seed admin and super admin users
        $this->call([
            UserSeeder::class,
        ]);
    }
}
