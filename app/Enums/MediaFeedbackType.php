<?php

namespace App\Enums;

enum MediaFeedbackType: string
{
    case TEXT = 'text';
    case VIDEO = 'video';
    case AUDIO = 'audio';

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
            self::TEXT => 'Text',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
        };
    }
}

