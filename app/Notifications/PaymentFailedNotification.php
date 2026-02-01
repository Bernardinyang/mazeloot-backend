<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
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
        $message = MailMessage::withLogo()
            ->subject('Payment Failed – Action Required')
            ->line('We could not process your subscription payment.')
            ->line('Please update your payment method to avoid losing access to your plan features.');

        if ($this->reason) {
            $message->line('**Reason:** '.$this->reason);
        }

        return $message
            ->action('Update Payment Method', config('app.frontend_url').'/memora/pricing')
            ->line('If you have questions, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        return ['reason' => $this->reason];
    }
}
