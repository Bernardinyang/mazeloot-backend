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
                    'textPosition' => 'center',
                    'textAlignment' => 'center',
                    'borders' => [
                        'enabled' => false,
                        'sides' => [],
                        'width' => 0,
                        'style' => 'solid',
                        'color' => 'accent',
                        'radius' => 0,
                    ],
                    'lines' => [
                        'horizontal' => [],
                        'vertical' => [],
                    ],
                    'dividers' => [
                        'enabled' => false,
                        'type' => 'vertical',
                        'position' => 0,
                        'width' => 0,
                        'color' => 'accent',
                        'style' => 'solid',
                    ],
                    'frame' => [
                        'enabled' => false,
                        'type' => 'full',
                        'sides' => [],
                        'width' => 0,
                        'color' => 'accent',
                        'padding' => 0,
                        'radius' => 0,
                    ],
                    'backgroundSections' => [],
                    'decorations' => [],
                    'specialLayout' => 'joy',
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
                    'textPosition' => 'bottom',
                    'textAlignment' => 'left',
                    'borders' => [
                        'enabled' => false,
                        'sides' => [],
                        'width' => 0,
                        'style' => 'solid',
                        'color' => 'accent',
                        'radius' => 0,
                    ],
                    'lines' => [
                        'horizontal' => [
                            [
                                'position' => 'above',
                                'offset' => 20,
                                'width' => 2,
                                'color' => 'accent',
                                'length' => 'partial',
                            ],
                        ],
                        'vertical' => [],
                    ],
                    'dividers' => [
                        'enabled' => false,
                        'type' => 'vertical',
                        'position' => 0,
                        'width' => 0,
                        'color' => 'accent',
                        'style' => 'solid',
                    ],
                    'frame' => [
                        'enabled' => false,
                        'type' => 'full',
                        'sides' => [],
                        'width' => 0,
                        'color' => 'accent',
                        'padding' => 0,
                        'radius' => 0,
                    ],
                    'backgroundSections' => [],
                    'decorations' => [],
                    'specialLayout' => null,
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
                    'textPosition' => 'center',
                    'textAlignment' => 'center',
                    'borders' => [
                        'enabled' => false,
                        'sides' => [],
                        'width' => 0,
                        'style' => 'solid',
                        'color' => 'accent',
                        'radius' => 0,
                    ],
                    'lines' => [
                        'horizontal' => [],
                        'vertical' => [],
                    ],
                    'dividers' => [
                        'enabled' => true,
                        'type' => 'vertical',
                        'position' => 50,
                        'width' => 2,
                        'color' => 'accent',
                        'style' => 'solid',
                    ],
                    'frame' => [
                        'enabled' => false,
                        'type' => 'full',
                        'sides' => [],
                        'width' => 0,
                        'color' => 'accent',
                        'padding' => 0,
                        'radius' => 0,
                    ],
                    'backgroundSections' => [
                        ['position' => 'left', 'width' => 50, 'color' => 'primary'],
                        ['position' => 'right', 'width' => 50, 'color' => 'secondary'],
                    ],
                    'decorations' => [],
                    'specialLayout' => 'split',
                ],
            ],
            [
                'name' => 'Full Image Background',
                'slug' => 'full-image-background',
                'description' => 'Full background image with text overlays at various positions',
                'is_active' => true,
                'is_default' => false,
                'order' => 4,
                'config' => [
                    'id' => 'full-image-background',
                    'label' => 'Full Image Background',
                    'textPosition' => 'center',
                    'textAlignment' => 'center',
                    'borders' => [
                        'enabled' => false,
                        'sides' => [],
                        'width' => 0,
                        'style' => 'solid',
                        'color' => 'accent',
                        'radius' => 0,
                    ],
                    'lines' => [
                        'horizontal' => [],
                        'vertical' => [],
                    ],
                    'dividers' => [
                        'enabled' => false,
                        'type' => 'vertical',
                        'position' => 0,
                        'width' => 0,
                        'color' => 'accent',
                        'style' => 'solid',
                    ],
                    'frame' => [
                        'enabled' => false,
                        'type' => 'full',
                        'sides' => [],
                        'width' => 0,
                        'color' => 'accent',
                        'padding' => 0,
                        'radius' => 0,
                    ],
                    'backgroundSections' => [],
                    'decorations' => [
                        ['type' => 'circle', 'position' => ['x' => 20, 'y' => 20], 'size' => 12, 'color' => 'accent', 'opacity' => 0.15],
                        ['type' => 'circle', 'position' => ['x' => 80, 'y' => 80], 'size' => 16, 'color' => 'accent', 'opacity' => 0.12],
                    ],
                    'specialLayout' => null,
                ],
            ],
            [
                'name' => 'None',
                'slug' => 'none',
                'description' => 'No cover photo, content as navbar',
                'is_active' => true,
                'is_default' => true,
                'order' => 5,
                'config' => [
                    'id' => 'none',
                    'label' => 'None',
                    'textPosition' => 'top',
                    'textAlignment' => 'left',
                    'borders' => [
                        'enabled' => false,
                        'sides' => [],
                        'width' => 0,
                        'style' => 'solid',
                        'color' => 'accent',
                        'radius' => 0,
                    ],
                    'lines' => [
                        'horizontal' => [],
                        'vertical' => [],
                    ],
                    'dividers' => [
                        'enabled' => false,
                        'type' => 'vertical',
                        'position' => 0,
                        'width' => 0,
                        'color' => 'accent',
                        'style' => 'solid',
                    ],
                    'frame' => [
                        'enabled' => false,
                        'type' => 'full',
                        'sides' => [],
                        'width' => 0,
                        'color' => 'accent',
                        'padding' => 0,
                        'radius' => 0,
                    ],
                    'backgroundSections' => [],
                    'decorations' => [],
                    'specialLayout' => 'none',
                ],
            ],
        ];

        foreach ($coverStyles as $style) {
            MemoraCoverStyle::query()->create($style);
        }
    }
}

