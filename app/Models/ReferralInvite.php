<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReferralInvite extends Model
{
    protected $table = 'referral_invites';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'referrer_user_uuid',
        'email',
        'sent_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_user_uuid', 'uuid');
    }
}
