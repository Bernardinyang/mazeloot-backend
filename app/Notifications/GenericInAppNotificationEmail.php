<?php

namespace App\Notifications;

use App\Models\Notification as NotificationModel;
use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GenericInAppNotificationEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NotificationModel $inAppNotification
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = $this->inAppNotification;
        $actionUrl = $n->action_url
            ? (str_starts_with($n->action_url, 'http') ? $n->action_url : config('app.frontend_url').$n->action_url)
            : null;

        $msg = MailMessage::withLogo()
            ->subject($n->title)
            ->line($n->message);

        if ($n->description) {
            $msg->line($n->description);
        }

        if ($actionUrl) {
            $msg->action('View in app', $actionUrl);
        }

        return $msg;
    }
}
