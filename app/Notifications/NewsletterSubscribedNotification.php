<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewsletterSubscribedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $newsletterUuid
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', '');
        $actionUrl = $frontendUrl ? rtrim($frontendUrl, '/').'/admin/newsletter' : null;

        $msg = MailMessage::withLogo()
            ->subject('New newsletter subscription: '.$this->email)
            ->line('A new person has subscribed to the newsletter.')
            ->line('**Email:** '.$this->email);

        if ($actionUrl) {
            $msg->action('View in admin', $actionUrl);
        }

        return $msg;
    }
}
