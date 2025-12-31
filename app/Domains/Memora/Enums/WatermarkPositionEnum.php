<?php

namespace App\Domains\Memora\Enums;

enum WatermarkPositionEnum: string
{
    case TOP_LEFT = 'top-left';
    case TOP_CENTER = 'top-center';
    case TOP_RIGHT = 'top-right';
    case CENTER = 'center';
    case BOTTOM_LEFT = 'bottom-left';
    case BOTTOM_CENTER = 'bottom-center';
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
            self::TOP_CENTER => 'Top Center',
            self::TOP_RIGHT => 'Top Right',
            self::CENTER => 'Center',
            self::BOTTOM_LEFT => 'Bottom Left',
            self::BOTTOM_CENTER => 'Bottom Center',
            self::BOTTOM_RIGHT => 'Bottom Right',
        };
    }
}
