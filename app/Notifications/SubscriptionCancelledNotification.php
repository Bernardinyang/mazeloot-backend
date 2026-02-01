<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?string $previousTier = null,
        public ?string $periodEnd = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = MailMessage::withLogo()
            ->subject('Subscription Cancelled')
            ->line('Your Memora subscription has been cancelled.')
            ->line('You have been downgraded to the **Starter** plan.');

        if ($this->periodEnd) {
            $message->line('Your paid access continues until **'.date('M j, Y', strtotime($this->periodEnd)).'**.');
        }

        return $message
            ->action('Upgrade Again', config('app.frontend_url').'/memora/pricing')
            ->line('Thank you for using Memora.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'previous_tier' => $this->previousTier,
            'period_end' => $this->periodEnd,
        ];
    }
}
