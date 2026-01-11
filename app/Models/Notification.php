<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

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
        'user_uuid',
        'product',
        'type',
        'title',
        'message',
        'description',
        'action_url',
        'metadata',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

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
        });
    }

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, string $userUuid): Builder
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * Scope to filter by product.
     */
    public function scopeForProduct(Builder $query, string $product): Builder
    {
        return $query->where('product', $product);
    }

    /**
     * Scope to filter unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to filter read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['read_at' => now()]);
    }

    /**
     * Check if notification is read.
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
