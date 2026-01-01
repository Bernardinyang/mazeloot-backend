<?php

namespace App\Domains\Memora\Enums;

enum WatermarkPositionEnum: string
{
    case TOP_LEFT = 'top-left';
    case TOP = 'top';
    case TOP_RIGHT = 'top-right';
    case LEFT = 'left';
    case CENTER = 'center';
    case RIGHT = 'right';
    case BOTTOM_LEFT = 'bottom-left';
    case BOTTOM = 'bottom';
    case BOTTOM_RIGHT = 'bottom-right';

    /**
     * Get all values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all names as array
     */
    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    /**
     * Get human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::TOP_LEFT => 'Top Left',
            self::TOP => 'Top',
            self::TOP_RIGHT => 'Top Right',
            self::LEFT => 'Left',
            self::CENTER => 'Center',
            self::RIGHT => 'Right',
            self::BOTTOM_LEFT => 'Bottom Left',
            self::BOTTOM => 'Bottom',
            self::BOTTOM_RIGHT => 'Bottom Right',
        };
    }
}
