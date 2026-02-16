<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class UserActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($notifiable->first_name.' '.$notifiable->last_name) ?: 'there';

        return MailMessage::withLogo()
            ->subject('Your account has been reactivated')
            ->line('Hello '.$name.',')
            ->line('Your Mazeloot account has been reactivated. You can sign in again and use the platform as usual.')
            ->action('Sign in', rtrim(config('app.frontend_url', ''), '/').'/login')
            ->line('Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
