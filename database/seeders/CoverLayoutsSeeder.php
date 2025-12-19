<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraCoverLayout;
use Illuminate\Database\Seeder;

class CoverLayoutsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coverLayouts = [
            [
                'name' => 'Hero Stack - Bottom Left',
                'slug' => 'hero-stack-bottom-left',
                'description' => 'Full-bleed hero image with text overlay positioned at bottom-left with gradient overlay',
                'is_active' => true,
                'is_default' => false,
                'order' => 1,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'bottom-left',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'bottom',
                        'opacity' => 0.55,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Hero Stack - Center',
                'slug' => 'hero-stack-center',
                'description' => 'Full-bleed hero image with centered text overlay and gradient',
                'is_active' => true,
                'is_default' => false,
                'order' => 2,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'middle-center',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'bottom',
                        'opacity' => 0.55,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Centered Card',
                'slug' => 'centered-card',
                'description' => 'Contained square image with content stacked below, centered layout',
                'is_active' => true,
                'is_default' => false,
                'order' => 3,
                'layout_config' => [
                    'layout' => 'column',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '1:1',
                        'fit' => 'cover',
                        'bleed' => 'contained',
                        'max_width' => 600,
                    ],
                    'content' => [
                        'placement' => 'below',
                        'alignment' => 'top-center',
                    ],
                    'overlay' => [
                        'enabled' => false,
                        'gradient' => 'none',
                        'opacity' => 0,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Row Layout - Image Right',
                'slug' => 'row-layout-image-right',
                'description' => 'Horizontal layout with content on left and image on right',
                'is_active' => true,
                'is_default' => false,
                'order' => 4,
                'layout_config' => [
                    'layout' => 'row',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '4:3',
                        'fit' => 'cover',
                        'bleed' => 'contained',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'side',
                        'alignment' => 'middle-left',
                    ],
                    'overlay' => [
                        'enabled' => false,
                        'gradient' => 'none',
                        'opacity' => 0,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Row Layout - Image Left',
                'slug' => 'row-layout-image-left',
                'description' => 'Horizontal layout with image on left and content on right',
                'is_active' => true,
                'is_default' => false,
                'order' => 5,
                'layout_config' => [
                    'layout' => 'row',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '4:3',
                        'fit' => 'cover',
                        'bleed' => 'contained',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'side',
                        'alignment' => 'middle-right',
                    ],
                    'overlay' => [
                        'enabled' => false,
                        'gradient' => 'none',
                        'opacity' => 0,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Framed Hero',
                'slug' => 'framed-hero',
                'description' => 'Full-bleed image with white border frame and centered content overlay',
                'is_active' => true,
                'is_default' => false,
                'order' => 6,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'middle-center',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'none',
                        'opacity' => 0.3,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Hero Stack - Top Left',
                'slug' => 'hero-stack-top-left',
                'description' => 'Full-bleed hero image with text overlay positioned at top-left',
                'is_active' => true,
                'is_default' => false,
                'order' => 7,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'top-left',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'top',
                        'opacity' => 0.55,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Hero Stack - Bottom Right',
                'slug' => 'hero-stack-bottom-right',
                'description' => 'Full-bleed hero image with text overlay positioned at bottom-right',
                'is_active' => true,
                'is_default' => false,
                'order' => 8,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'bottom-right',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'bottom',
                        'opacity' => 0.55,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Wide Card',
                'slug' => 'wide-card',
                'description' => 'Contained wide image with content below, optimized for horizontal displays',
                'is_active' => true,
                'is_default' => false,
                'order' => 9,
                'layout_config' => [
                    'layout' => 'column',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'contained',
                        'max_width' => 1200,
                    ],
                    'content' => [
                        'placement' => 'below',
                        'alignment' => 'top-center',
                    ],
                    'overlay' => [
                        'enabled' => false,
                        'gradient' => 'none',
                        'opacity' => 0,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Portrait Card',
                'slug' => 'portrait-card',
                'description' => 'Contained portrait-oriented image with content below',
                'is_active' => true,
                'is_default' => false,
                'order' => 10,
                'layout_config' => [
                    'layout' => 'column',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '3:4',
                        'fit' => 'cover',
                        'bleed' => 'contained',
                        'max_width' => 600,
                    ],
                    'content' => [
                        'placement' => 'below',
                        'alignment' => 'top-center',
                    ],
                    'overlay' => [
                        'enabled' => false,
                        'gradient' => 'none',
                        'opacity' => 0,
                    ],
                    'spacing' => [
                        'padding_x' => 80,
                        'padding_y' => 60,
                    ],
                ],
            ],
            [
                'name' => 'Minimal Stack',
                'slug' => 'minimal-stack',
                'description' => 'Full-bleed image with minimal overlay and subtle content placement',
                'is_active' => true,
                'is_default' => true,
                'order' => 11,
                'layout_config' => [
                    'layout' => 'stack',
                    'media' => [
                        'type' => 'image',
                        'aspect_ratio' => '16:9',
                        'fit' => 'cover',
                        'bleed' => 'full',
                        'max_width' => null,
                    ],
                    'content' => [
                        'placement' => 'overlay',
                        'alignment' => 'bottom-left',
                    ],
                    'overlay' => [
                        'enabled' => true,
                        'gradient' => 'bottom',
                        'opacity' => 0.4,
                    ],
                    'spacing' => [
                        'padding_x' => 60,
                        'padding_y' => 40,
                    ],
                ],
            ],
        ];

        foreach ($coverLayouts as $layout) {
            MemoraCoverLayout::query()->create($layout);
        }
    }
}
