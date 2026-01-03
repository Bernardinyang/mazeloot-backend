<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraPreset;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PresetTemplatesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Wedding Collection',
                'description' => 'Perfect for wedding galleries with elegant settings',
                'email_registration' => true,
                'gallery_assist' => true,
                'slideshow' => true,
                'social_sharing' => true,
                'language' => 'en',
                'design_font_family' => 'serif',
                'design_color_palette' => 'light',
                'design_grid_style' => 'vertical',
                'design_grid_columns' => 3,
                'privacy_show_on_homepage' => true,
                'download_photo_download' => true,
                'download_high_resolution_enabled' => true,
                'favorite_favorite_enabled' => true,
            ],
            [
                'name' => 'Portrait Session',
                'description' => 'Ideal for portrait photography sessions',
                'email_registration' => false,
                'gallery_assist' => false,
                'slideshow' => true,
                'social_sharing' => false,
                'language' => 'en',
                'design_font_family' => 'sans',
                'design_color_palette' => 'dark',
                'design_grid_style' => 'masonry',
                'design_grid_columns' => 4,
                'privacy_show_on_homepage' => false,
                'download_photo_download' => true,
                'download_high_resolution_enabled' => true,
                'favorite_favorite_enabled' => true,
            ],
            [
                'name' => 'Event Gallery',
                'description' => 'Great for events, parties, and celebrations',
                'email_registration' => true,
                'gallery_assist' => true,
                'slideshow' => true,
                'social_sharing' => true,
                'language' => 'en',
                'design_font_family' => 'sans',
                'design_color_palette' => 'light',
                'design_grid_style' => 'vertical',
                'design_grid_columns' => 3,
                'privacy_show_on_homepage' => true,
                'download_photo_download' => true,
                'download_high_resolution_enabled' => false,
                'favorite_favorite_enabled' => true,
            ],
        ];

        foreach ($templates as $template) {
            MemoraPreset::create($template);
        }
    }
}

