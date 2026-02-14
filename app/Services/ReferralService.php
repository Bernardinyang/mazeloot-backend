<?php

namespace App\Services;

use App\Jobs\SendReferralInviteEmail;
use App\Models\ReferralInvite;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public function getOrCreateCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }
        $code = Str::upper(Str::random(10));
        while (User::where('referral_code', $code)->exists()) {
            $code = Str::upper(Str::random(10));
        }
        $user->referral_code = $code;
        $user->save();

        return $code;
    }

    public function getReferralLink(User $user): string
    {
        $code = $this->getOrCreateCode($user);
        $base = rtrim(config('app.frontend_url', config('app.url')), '/');

        return "{$base}/ref/{$code}";
    }

    public function getStats(User $user): array
    {
        $conversions = ReferralInvite::where('referrer_user_uuid', $user->uuid)
            ->whereNotNull('converted_at')
            ->count();
        $registered = (int) User::where('referred_by_user_uuid', $user->uuid)->count();

        return [
            'total_registered' => $registered,
            'total_conversions' => $conversions,
            'total_earned_cents' => $conversions * 2000, // $20 per conversion
            'credit_balance_cents' => 0, // TODO: when credits are applied to billing
        ];
    }

    /**
     * @return array<int, array{email: string, sent_at: string, converted_at: string|null, status: string}>
     */
    public function listReferrals(User $user): array
    {
        $invites = ReferralInvite::where('referrer_user_uuid', $user->uuid)
            ->orderByDesc('sent_at')
            ->get();
        $referredUsers = User::where('referred_by_user_uuid', $user->uuid)->get();
        $inviteByEmail = $invites->keyBy('email');

        $rows = $invites->map(function (ReferralInvite $invite) use ($referredUsers) {
            $status = $invite->converted_at
                ? 'converted'
                : ($referredUsers->contains('email', $invite->email) ? 'registered' : 'pending');

            return [
                'email' => $invite->email,
                'sent_at' => $invite->sent_at->toIso8601String(),
                'converted_at' => $invite->converted_at?->toIso8601String(),
                'status' => $status,
            ];
        });

        foreach ($referredUsers as $referred) {
            if ($inviteByEmail->has($referred->email)) {
                continue;
            }
            $rows->push([
                'email' => $referred->email,
                'sent_at' => $referred->created_at?->toIso8601String() ?? now()->toIso8601String(),
                'converted_at' => null,
                'status' => 'registered',
            ]);
        }

        return $rows->sortByDesc(fn ($r) => $r['sent_at'])->values()->all();
    }

    public function sendInvite(User $referrer, string $email): void
    {
        ReferralInvite::create([
            'referrer_user_uuid' => $referrer->uuid,
            'email' => $email,
            'sent_at' => now(),
        ]);

        $link = $this->getReferralLink($referrer);
        $referrerName = trim($referrer->first_name.' '.$referrer->last_name) ?: 'A friend';
        SendReferralInviteEmail::dispatch($email, $link, $referrerName);
    }

    public function resolveReferrerByCode(string $code): ?User
    {
        if (strlen($code) < 5) {
            return null;
        }

        return User::where('referral_code', strtoupper($code))->first();
    }

    public function markConverted(User $referredUser): void
    {
        if (! $referredUser->referred_by_user_uuid) {
            return;
        }

        $updated = ReferralInvite::where('referrer_user_uuid', $referredUser->referred_by_user_uuid)
            ->where('email', $referredUser->email)
            ->whereNull('converted_at')
            ->update(['converted_at' => now()]);

        if ($updated === 0) {
            ReferralInvite::firstOrCreate(
                [
                    'referrer_user_uuid' => $referredUser->referred_by_user_uuid,
                    'email' => $referredUser->email,
                ],
                ['sent_at' => $referredUser->created_at ?? now(), 'converted_at' => now()],
            );
        }
    }
}
