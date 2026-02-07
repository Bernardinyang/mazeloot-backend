<?php

namespace App\Notifications;

use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WaitlistConfirmationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $productName
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return MailMessage::withLogo()
            ->subject('You\'re on the waitlist for '.$this->productName)
            ->greeting('Hi '.$this->name.',')
            ->line('Thank you for joining the waitlist for **'.$this->productName.'**!')
            ->line('We\'re excited to have you on board. You\'ll be among the first to know when we officially launch.')
            ->line('What happens next?')
            ->line('• We\'ll notify you as soon as '.$this->productName.' is available')
            ->line('• You\'ll get early access and exclusive benefits')
            ->line('• Stay tuned for updates and special offers')
            ->line('We appreciate your patience and can\'t wait to share what we\'ve been building.')
            ->line('If you have any questions, feel free to reach out to us anytime.');
    }
}
