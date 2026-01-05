<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $code
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return MailMessage::withLogo()
            ->subject('Verify Your Email Address')
            ->line('Thank you for registering with '.config('app.name').'.')
            ->line('Your verification code is: **'.$this->code.'**')
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not create an account, no further action is required.');
    }
}
