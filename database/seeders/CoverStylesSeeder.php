<?php

namespace Database\Seeders;

use App\Domains\Memora\Models\MemoraCoverStyle;
use Illuminate\Database\Seeder;

class CoverStylesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coverStyles = [
            [
                'name' => 'Joy',
                'slug' => 'joy',
                'description' => 'Special layout with title, avatar in letter (like O), date, name, and button',
                'is_active' => true,
                'is_default' => false,
                'order' => 1,
                'config' => [
                    'id' => 'joy',
                    'label' => 'Joy',
                    'layoutConfig' => [
                        'layout' => 'joy',
                        'media' => [
                            'type' => 'image',
                            'aspect_ratio' => '16:9',
                            'fit' => 'cover',
                            'bleed' => 'full',
                            'max_width' => null,
                        ],
                        'content' => [
                            'placement' => 'overlay',
                            'alignment' => 'center',
                        ],
                        'overlay' => [
                            'enabled' => true,
                            'gradient' => 'radial',
                            'opacity' => 0.3,
                        ],
                        'spacing' => [
                            'padding_x' => 80,
                            'padding_y' => 60,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Portrait Overlay',
                'slug' => 'portrait-overlay',
                'description' => 'Portrait image background with text overlays positioned on top',
                'is_active' => true,
                'is_default' => false,
                'order' => 2,
                'config' => [
                    'id' => 'portrait-overlay',
                    'label' => 'Portrait Overlay',
                    'layoutConfig' => [
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
                            'opacity' => 0.6,
                        ],
                        'spacing' => [
                            'padding_x' => 80,
                            'padding_y' => 80,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Split Layout',
                'slug' => 'split-layout',
                'description' => 'Split layout with text content on one side and image on the other',
                'is_active' => true,
                'is_default' => false,
                'order' => 3,
                'config' => [
                    'id' => 'split-layout',
                    'label' => 'Split Layout',
                    'layoutConfig' => [
                        'layout' => 'split',
                        'media' => [
                            'type' => 'image',
                            'aspect_ratio' => '4:3',
                            'fit' => 'cover',
                            'bleed' => 'none',
                            'max_width' => 50,
                            'position' => 'left',
                        ],
                        'content' => [
                            'placement' => 'side',
                            'alignment' => 'center',
                        ],
                        'overlay' => [
                            'enabled' => false,
                            'gradient' => 'none',
                            'opacity' => 0,
                        ],
                        'spacing' => [
                            'padding_x' => 60,
                            'padding_y' => 40,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Center Full Image',
                'slug' => 'center-full-image',
                'description' => 'Full background image with centered text overlay',
                'is_active' => true,
                'is_default' => false,
                'order' => 4,
                'config' => [
                    'id' => 'center-full-image',
                    'label' => 'Center Full Image',
                    'layoutConfig' => [
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
                            'alignment' => 'center',
                        ],
                        'overlay' => [
                            'enabled' => true,
                            'gradient' => 'radial',
                            'opacity' => 0.4,
                        ],
                        'spacing' => [
                            'padding_x' => 80,
                            'padding_y' => 60,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Left Side Image',
                'slug' => 'left-side-image',
                'description' => 'Image on the left side with content on the right',
                'is_active' => true,
                'is_default' => false,
                'order' => 5,
                'config' => [
                    'id' => 'left-side-image',
                    'label' => 'Left Side Image',
                    'layoutConfig' => [
                        'layout' => 'split',
                        'media' => [
                            'type' => 'image',
                            'aspect_ratio' => '4:3',
                            'fit' => 'cover',
                            'bleed' => 'none',
                            'max_width' => 50,
                            'position' => 'left',
                        ],
                        'content' => [
                            'placement' => 'side',
                            'alignment' => 'left',
                        ],
                        'overlay' => [
                            'enabled' => false,
                            'gradient' => 'none',
                            'opacity' => 0,
                        ],
                        'spacing' => [
                            'padding_x' => 60,
                            'padding_y' => 40,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Right Side Image',
                'slug' => 'right-side-image',
                'description' => 'Image on the right side with content on the left',
                'is_active' => true,
                'is_default' => false,
                'order' => 6,
                'config' => [
                    'id' => 'right-side-image',
                    'label' => 'Right Side Image',
                    'layoutConfig' => [
                        'layout' => 'split',
                        'media' => [
                            'type' => 'image',
                            'aspect_ratio' => '4:3',
                            'fit' => 'cover',
                            'bleed' => 'none',
                            'max_width' => 50,
                            'position' => 'right',
                        ],
                        'content' => [
                            'placement' => 'side',
                            'alignment' => 'right',
                        ],
                        'overlay' => [
                            'enabled' => false,
                            'gradient' => 'none',
                            'opacity' => 0,
                        ],
                        'spacing' => [
                            'padding_x' => 60,
                            'padding_y' => 40,
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Bottom Left Overlay',
                'slug' => 'bottom-left-overlay',
                'description' => 'Full background image with text at bottom left',
                'is_active' => true,
                'is_default' => false,
                'order' => 7,
                'config' => [
                    'id' => 'bottom-left-overlay',
                    'label' => 'Bottom Left Overlay',
                    'layoutConfig' => [
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
            ],
            [
                'name' => 'None',
                'slug' => 'none',
                'description' => 'No cover photo, content as navbar',
                'is_active' => true,
                'is_default' => true,
                'order' => 8,
                'config' => [
                    'id' => 'none',
                    'label' => 'None',
                    'layoutConfig' => [
                        'layout' => 'none',
                        'media' => [
                            'type' => 'image',
                            'aspect_ratio' => '16:9',
                            'fit' => 'cover',
                            'bleed' => 'none',
                            'max_width' => null,
                        ],
                        'content' => [
                            'placement' => 'below',
                            'alignment' => 'center',
                        ],
                        'overlay' => [
                            'enabled' => false,
                            'gradient' => 'none',
                            'opacity' => 0,
                        ],
                        'spacing' => [
                            'padding_x' => 0,
                            'padding_y' => 0,
                        ],
                    ],
                ],
            ],
        ];

        foreach ($coverStyles as $style) {
            MemoraCoverStyle::query()->create($style);
        }
    }
}

