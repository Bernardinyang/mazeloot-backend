<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SubscriptionRenewedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tier,
        public string $billingCycle,
        public ?string $periodEnd = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tierLabel = $this->tier === 'byo' ? 'Build Your Own' : ucfirst($this->tier);
        $message = MailMessage::withLogo()
            ->subject('Subscription Renewed – '.$tierLabel)
            ->line('Your **'.$tierLabel.'** plan has been successfully renewed.')
            ->line('Thank you for continuing with Memora.');

        if ($this->periodEnd) {
            $message->line('Your next billing date is **'.date('M j, Y', strtotime($this->periodEnd)).'**.');
        }

        return $message->action('View Usage', config('app.frontend_url').'/memora/usage');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'tier' => $this->tier,
            'billing_cycle' => $this->billingCycle,
            'period_end' => $this->periodEnd,
        ];
    }
}
