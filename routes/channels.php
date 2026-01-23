<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('user.{userId}', function ($user, $userId) {
    \Illuminate\Support\Facades\Log::info('Broadcast channel authorization', [
        'channel' => 'user.'.$userId,
        'user_id' => $user?->uuid ?? $user?->id ?? 'null',
        'requested_user_id' => $userId,
    ]);

    if (! $user) {
        \Illuminate\Support\Facades\Log::warning('Broadcast authorization failed: no user');

        return false;
    }

    $userUuid = $user->uuid ?? $user->id ?? null;
    if (! $userUuid) {
        \Illuminate\Support\Facades\Log::warning('Broadcast authorization failed: no user UUID', [
            'user_id' => $user->id ?? 'null',
        ]);

        return false;
    }

    $authorized = (string) $userUuid === (string) $userId;
    \Illuminate\Support\Facades\Log::info('Broadcast authorization result', [
        'authorized' => $authorized,
        'user_uuid' => $userUuid,
        'requested_uuid' => $userId,
    ]);

    return $authorized;
});

Broadcast::channel('admin.early-access', function ($user) {
    return $user && $user->isAdmin();
});
