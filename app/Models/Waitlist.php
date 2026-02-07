<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Waitlist extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'product_uuid',
        'status',
        'user_uuid',
        'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'not_registered';
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_uuid', 'uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function isNotRegistered(): bool
    {
        return $this->status === 'not_registered';
    }

    public function isRegistered(): bool
    {
        return $this->status === 'registered';
    }

    public function markAsRegistered(?string $userUuid = null): void
    {
        $this->update([
            'status' => 'registered',
            'user_uuid' => $userUuid,
            'registered_at' => now(),
        ]);
    }
}
