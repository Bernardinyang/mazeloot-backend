<?php

namespace Database\Seeders;

use App\Models\UserStatus;
use Illuminate\Database\Seeder;

class UserStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'active',
                'description' => 'User account is active and can access all features',
                'color' => '#10B981', // Green
            ],
            [
                'name' => 'inactive',
                'description' => 'User account is inactive and cannot log in',
                'color' => '#6B7280', // Gray
            ],
            [
                'name' => 'suspended',
                'description' => 'User account has been temporarily suspended',
                'color' => '#F59E0B', // Amber
            ],
            [
                'name' => 'banned',
                'description' => 'User account has been permanently banned',
                'color' => '#EF4444', // Red
            ],
            [
                'name' => 'pending',
                'description' => 'User account is pending activation',
                'color' => '#3B82F6', // Blue
            ],
        ];

        foreach ($statuses as $status) {
            UserStatus::firstOrCreate(
                ['name' => $status['name']],
                [
                    'description' => $status['description'],
                    'color' => $status['color'],
                ]
            );
        }
    }
}
