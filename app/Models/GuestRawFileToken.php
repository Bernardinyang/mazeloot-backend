<?php

namespace App\Models;

use App\Domains\Memora\Models\MemoraRawFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuestRawFileToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'guest_raw_file_tokens';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    protected $fillable = [
        'raw_file_uuid',
        'email',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->token)) {
                $model->token = Str::random(64);
            }
        });
    }

    /**
     * Get the raw file this token belongs to
     */
    public function rawFile(): BelongsTo
    {
        return $this->belongsTo(MemoraRawFile::class, 'raw_file_uuid', 'uuid');
    }

    /**
     * Check if token is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token has been used
     */
    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
