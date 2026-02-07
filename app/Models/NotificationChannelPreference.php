<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NotificationChannelPreference extends Model
{
    protected $table = 'notification_channel_preferences';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_uuid',
        'product',
        'notify_email',
        'notify_in_app',
        'notify_whatsapp',
        'whatsapp_number',
    ];

    protected function casts(): array
    {
        return [
            'notify_email' => 'boolean',
            'notify_in_app' => 'boolean',
            'notify_whatsapp' => 'boolean',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
}
