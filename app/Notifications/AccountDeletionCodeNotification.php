<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccountDeletionCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $code
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return MailMessage::withLogo()
            ->subject('Confirm Account Deletion')
            ->line('You requested to delete your '.config('app.name').' account.')
            ->line('Your confirmation code is: **'.$this->code.'**')
            ->line('This code expires in 15 minutes. Enter it in the app to confirm deletion.')
            ->line('If you did not request this, you can ignore this email. Your account is safe.');
    }
}
