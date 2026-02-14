<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReferralInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $referralLink,
        public string $referrerName = 'A friend'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $msg = MailMessage::withLogo()
            ->subject($this->referrerName.' invited you to Mazeloot — get $20 off')
            ->line($this->referrerName.' thought you might like Mazeloot.')
            ->line('Sign up with the link below and get **$20 off** your first bill when you subscribe.')
            ->action('Accept invitation', $this->referralLink)
            ->line('If you weren\'t expecting this, you can ignore this email.');

        return $msg;
    }
}
