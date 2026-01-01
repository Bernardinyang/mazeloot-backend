<?php

namespace Database\Seeders;

use App\Models\SocialMediaPlatform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SocialMediaPlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $platforms = [
            [
                'name' => 'Instagram',
                'slug' => 'instagram',
                'icon' => 'instagram',
                'base_url' => 'https://instagram.com/',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'name' => 'Facebook',
                'slug' => 'facebook',
                'icon' => 'facebook',
                'base_url' => 'https://facebook.com/',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'name' => 'Twitter/X',
                'slug' => 'twitter',
                'icon' => 'twitter',
                'base_url' => 'https://twitter.com/',
                'is_active' => true,
                'order' => 3,
            ],
            [
                'name' => 'LinkedIn',
                'slug' => 'linkedin',
                'icon' => 'linkedin',
                'base_url' => 'https://linkedin.com/in/',
                'is_active' => true,
                'order' => 4,
            ],
            [
                'name' => 'YouTube',
                'slug' => 'youtube',
                'icon' => 'youtube',
                'base_url' => 'https://youtube.com/',
                'is_active' => true,
                'order' => 5,
            ],
            [
                'name' => 'Pinterest',
                'slug' => 'pinterest',
                'icon' => 'pinterest',
                'base_url' => 'https://pinterest.com/',
                'is_active' => true,
                'order' => 6,
            ],
            [
                'name' => 'TikTok',
                'slug' => 'tiktok',
                'icon' => 'tiktok',
                'base_url' => 'https://tiktok.com/@',
                'is_active' => true,
                'order' => 7,
            ],
            [
                'name' => 'Behance',
                'slug' => 'behance',
                'icon' => 'behance',
                'base_url' => 'https://behance.net/',
                'is_active' => true,
                'order' => 8,
            ],
            [
                'name' => 'Dribbble',
                'slug' => 'dribbble',
                'icon' => 'dribbble',
                'base_url' => 'https://dribbble.com/',
                'is_active' => true,
                'order' => 9,
            ],
            [
                'name' => 'Vimeo',
                'slug' => 'vimeo',
                'icon' => 'vimeo',
                'base_url' => 'https://vimeo.com/',
                'is_active' => true,
                'order' => 10,
            ],
            [
                'name' => 'VSCO',
                'slug' => 'vsco',
                'icon' => 'vsco',
                'base_url' => 'https://vsco.co/',
                'is_active' => true,
                'order' => 11,
            ],
            [
                'name' => 'ArtStation',
                'slug' => 'artstation',
                'icon' => 'artstation',
                'base_url' => 'https://artstation.com/',
                'is_active' => true,
                'order' => 12,
            ],
            [
                'name' => '500px',
                'slug' => '500px',
                'icon' => '500px',
                'base_url' => 'https://500px.com/',
                'is_active' => true,
                'order' => 13,
            ],
            [
                'name' => 'Flickr',
                'slug' => 'flickr',
                'icon' => 'flickr',
                'base_url' => 'https://flickr.com/photos/',
                'is_active' => true,
                'order' => 14,
            ],
        ];

        foreach ($platforms as $platform) {
            SocialMediaPlatform::updateOrCreate(
                ['slug' => $platform['slug']],
                array_merge($platform, ['uuid' => (string) Str::uuid()])
            );
        }
    }
}
