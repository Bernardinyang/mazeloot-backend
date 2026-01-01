<?php

namespace App\Domains\Memora\Enums;

enum FontStyleEnum: string
{
    case NORMAL = 'normal';
    case BOLD = 'bold';
    case ITALIC = 'italic';
    case BOLD_ITALIC = 'bold-italic';

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
            self::NORMAL => 'Normal',
            self::BOLD => 'Bold',
            self::ITALIC => 'Italic',
            self::BOLD_ITALIC => 'Bold Italic',
        };
    }
}
