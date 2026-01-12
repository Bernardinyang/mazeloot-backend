<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'id' => 'memora',
                'name' => 'Memora',
                'display_name' => 'Memora',
                'description' => 'Client Photo Gallery / Collections',
                'custom_type' => 'memora',
                'slug' => 'memora',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'id' => 'connect-stream',
                'name' => 'Connect Stream',
                'display_name' => 'Connect Stream',
                'description' => 'Community / Newsfeed',
                'custom_type' => 'connect-stream',
                'slug' => 'connect-stream',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'id' => 'creator-iq',
                'name' => 'Creator IQ',
                'display_name' => 'Creator IQ',
                'description' => 'Analytics (Creative)',
                'custom_type' => 'creator-iq',
                'slug' => 'creator-iq',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'id' => 'gear-hub',
                'name' => 'Gear Hub',
                'display_name' => 'Gear Hub',
                'description' => 'Marketplace / Rental (for physical product)',
                'custom_type' => 'gear-hub',
                'slug' => 'gear-hub',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'id' => 'vendor-iq',
                'name' => 'Vendor IQ',
                'display_name' => 'Vendor IQ',
                'description' => 'Analytics (Marketplace)',
                'custom_type' => 'vendor-iq',
                'slug' => 'vendor-iq',
                'is_active' => true,
                'order' => 5,
            ],
            [
                'id' => 'gig-finder',
                'name' => 'GigFinder',
                'display_name' => 'GigFinder',
                'description' => 'Job Listing',
                'custom_type' => 'gig-finder',
                'slug' => 'gig-finder',
                'is_active' => true,
                'order' => 6,
            ],
            [
                'id' => 'profolio',
                'name' => 'Profolio',
                'display_name' => 'Profolio',
                'description' => 'Services / Portfolio',
                'custom_type' => 'profolio',
                'slug' => 'profolio',
                'is_active' => true,
                'order' => 7,
            ],
        ];

        foreach ($products as $product) {
            Product::firstOrCreate(
                ['id' => $product['id']],
                $product
            );
        }
    }
}
