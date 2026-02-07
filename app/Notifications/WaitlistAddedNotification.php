<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WaitlistAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
        public string $productName,
        public string $waitlistUuid
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = config('app.frontend_url', '');
        $actionUrl = $frontendUrl ? rtrim($frontendUrl, '/')."/admin/waitlist/{$this->waitlistUuid}" : null;

        $msg = MailMessage::withLogo()
            ->subject('New waitlist signup: '.$this->name.' ('.$this->productName.')')
            ->line('A new person has joined the waitlist.')
            ->line('**Name:** '.$this->name)
            ->line('**Email:** '.$this->email)
            ->line('**Product:** '.$this->productName);

        if ($actionUrl) {
            $msg->action('View in admin', $actionUrl);
        }

        return $msg;
    }
}
