<?php

namespace App\Domains\Memora\Enums;

enum BorderStyle: string
{
    case NONE = 'none';
    case SOLID = 'solid';
    case DASHED = 'dashed';
    case DOTTED = 'dotted';

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
            self::NONE => 'None',
            self::SOLID => 'Solid',
            self::DASHED => 'Dashed',
            self::DOTTED => 'Dotted',
        };
    }
}

