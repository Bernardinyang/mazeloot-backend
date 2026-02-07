<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ContactFormSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public array $payload
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payload;
        $name = trim(($p['first_name'] ?? '').' '.($p['last_name'] ?? ''));

        $msg = MailMessage::withLogo()
            ->subject('Contact form: '.$name.' ('.$p['email'].')')
            ->line('New contact form submission from '.$name.'.')
            ->line('**Email:** '.($p['email'] ?? '—'))
            ->line('**Company:** '.($p['company'] ?? '—'));

        if (! empty($p['phone'])) {
            $msg->line('**Phone:** '.$p['phone']);
        }

        if (! empty($p['message'])) {
            $msg->line('**Message:**')->line($p['message']);
        }

        $frontendUrl = config('app.frontend_url', '');
        if ($frontendUrl) {
            $msg->action('View in admin', rtrim($frontendUrl, '/').'/admin');
        }

        return $msg;
    }
}
