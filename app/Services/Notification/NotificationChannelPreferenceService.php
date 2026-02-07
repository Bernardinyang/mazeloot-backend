<?php

namespace App\Services\Notification;

use App\Models\NotificationChannelPreference;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotificationChannelPreferenceService
{
    public function getForUser(string $product = 'memora'): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $pref = NotificationChannelPreference::where('user_uuid', $user->uuid)
            ->where('product', $product)
            ->first();

        if (! $pref) {
            return [
                'notify_email' => true,
                'notify_in_app' => true,
                'notify_whatsapp' => false,
                'whatsapp_number' => null,
            ];
        }

        return [
            'notify_email' => $pref->notify_email,
            'notify_in_app' => $pref->notify_in_app,
            'notify_whatsapp' => $pref->notify_whatsapp,
            'whatsapp_number' => $pref->whatsapp_number,
        ];
    }

    public function getForUserUuid(string $userUuid, string $product = 'memora'): ?NotificationChannelPreference
    {
        return NotificationChannelPreference::where('user_uuid', $userUuid)
            ->where('product', $product)
            ->first();
    }

    public function update(string $product, array $data): array
    {
        $user = Auth::user();
        if (! $user) {
            throw new \Illuminate\Auth\AuthenticationException('User not authenticated');
        }

        $pref = NotificationChannelPreference::updateOrCreate(
            [
                'user_uuid' => $user->uuid,
                'product' => $product,
            ],
            [
                'notify_email' => $data['notify_email'] ?? true,
                'notify_in_app' => $data['notify_in_app'] ?? true,
                'notify_whatsapp' => $data['notify_whatsapp'] ?? false,
                'whatsapp_number' => $data['notify_whatsapp'] ? ($data['whatsapp_number'] ?? null) : null,
            ]
        );

        return [
            'notify_email' => $pref->notify_email,
            'notify_in_app' => $pref->notify_in_app,
            'notify_whatsapp' => $pref->notify_whatsapp,
            'whatsapp_number' => $pref->whatsapp_number,
        ];
    }

    public function getOrCreateForUser(User $user, string $product = 'memora'): NotificationChannelPreference
    {
        return NotificationChannelPreference::firstOrCreate(
            [
                'user_uuid' => $user->uuid,
                'product' => $product,
            ],
            [
                'notify_email' => true,
                'notify_in_app' => true,
                'notify_whatsapp' => false,
                'whatsapp_number' => null,
            ]
        );
    }
}
