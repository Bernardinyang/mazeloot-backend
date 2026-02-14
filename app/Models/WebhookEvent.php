<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebhookEvent extends Model
{
    protected $table = 'webhook_events';

    protected $fillable = [
        'provider',
        'event_type',
        'event_id',
        'status',
        'response_code',
        'error_message',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
        ];
    }

    public static function record(
        string $provider,
        string $status,
        ?int $responseCode = null,
        ?string $eventType = null,
        ?string $eventId = null,
        ?string $errorMessage = null
    ): void {
        try {
            self::create([
                'provider' => $provider,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'status' => $status,
                'response_code' => $responseCode,
                'error_message' => $errorMessage ? Str::limit($errorMessage, 1000) : null,
                'received_at' => now(),
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to record webhook event', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
