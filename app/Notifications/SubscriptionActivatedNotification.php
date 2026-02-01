<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionActivatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tier,
        public string $billingCycle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tierLabel = $this->tier === 'byo' ? 'Build Your Own' : ucfirst($this->tier);
        $cycleLabel = $this->billingCycle === 'annual' ? 'annual' : 'monthly';

        return MailMessage::withLogo()
            ->subject('Subscription Activated – Welcome to '.$tierLabel)
            ->line('Your **'.$tierLabel.'** plan ('.$cycleLabel.' billing) is now active.')
            ->line('You now have access to all features included in your plan.')
            ->action('View Usage & Plan', config('app.frontend_url').'/memora/usage')
            ->line('Thank you for subscribing!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tier' => $this->tier,
            'billing_cycle' => $this->billingCycle,
        ];
    }
}
