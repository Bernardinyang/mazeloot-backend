<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'slug' => 'memora',
                'name' => 'Memora',
                'description' => 'Client Gallery - Share, deliver, proof and sell photos online with beautiful photo galleries.',
                'icon' => 'images',
                'is_active' => true,
                'order' => 1,
                'metadata' => [
                    'onboarding_steps' => ['branding', 'domain'],
                ],
            ],
            [
                'slug' => 'connect_stream',
                'name' => 'Connect Stream',
                'description' => 'Connect and stream with your clients seamlessly.',
                'icon' => 'stream',
                'is_active' => false,
                'order' => 2,
                'metadata' => [],
            ],
            [
                'slug' => 'creator_iq',
                'name' => 'Creator IQ',
                'description' => 'Intelligence and insights for creators.',
                'icon' => 'brain',
                'is_active' => false,
                'order' => 3,
                'metadata' => [],
            ],
            [
                'slug' => 'gear_hub',
                'name' => 'Gear Hub',
                'description' => 'Manage and track your photography gear.',
                'icon' => 'camera',
                'is_active' => false,
                'order' => 4,
                'metadata' => [],
            ],
            [
                'slug' => 'vendor_iq',
                'name' => 'Vendor IQ',
                'description' => 'Vendor management and intelligence.',
                'icon' => 'store',
                'is_active' => false,
                'order' => 5,
                'metadata' => [],
            ],
            [
                'slug' => 'gigfinder',
                'name' => 'GigFinder',
                'description' => 'Find and manage photography gigs.',
                'icon' => 'search',
                'is_active' => false,
                'order' => 6,
                'metadata' => [],
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['slug' => $product['slug']],
                $product
            );
        }
    }
}
