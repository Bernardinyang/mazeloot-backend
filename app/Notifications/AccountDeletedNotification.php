<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AccountDeletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $userName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->userName ?: 'there';

        return MailMessage::withLogo()
            ->subject('Your account has been deleted')
            ->line('Hello '.$name.',')
            ->line('This email confirms that your Mazeloot account has been permanently deleted.')
            ->line('All your data has been removed from our systems. If you did not request this, please contact support immediately.')
            ->line('Thank you for having used our services.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
