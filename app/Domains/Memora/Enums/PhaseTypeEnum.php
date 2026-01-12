<?php

namespace App\Domains\Memora\Enums;

enum PhaseTypeEnum: string
{
    case SELECTION = 'selection';
    case PROOFING = 'proofing';
    case COLLECTION = 'collection';
    case RAW_FILES = 'raw_files';

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
            self::SELECTION => 'MemoraSelection',
            self::PROOFING => 'MemoraProofing',
            self::COLLECTION => 'MemoraCollection',
            self::RAW_FILES => 'MemoraRawFiles',
        };
    }
}
