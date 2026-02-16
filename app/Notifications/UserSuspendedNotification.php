<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UserSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->first_name.' '.$notifiable->last_name) ?: 'there';

        $message = MailMessage::withLogo()
            ->subject('Your account has been suspended')
            ->line('Hello '.$name.',')
            ->line('Your Mazeloot account has been suspended. You will not be able to sign in until your account is reactivated.');

        if ($this->reason) {
            $message->line('**Reason for suspension:**')
                ->line($this->reason);
        }

        $message->line('If you believe this is an error or would like to appeal, please contact our support team.');

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return ['reason' => $this->reason];
    }
}
