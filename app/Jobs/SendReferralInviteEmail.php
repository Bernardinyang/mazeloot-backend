<?php

namespace App\Jobs;

use App\Notifications\ReferralInviteNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class SendReferralInviteEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(
        public string $email,
        public string $referralLink,
        public string $referrerName = 'A friend'
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        Notification::route('mail', $this->email)
            ->notify(new ReferralInviteNotification($this->referralLink, $this->referrerName));
    }
}
