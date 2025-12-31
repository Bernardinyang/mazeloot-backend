<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Services\ClosureRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProofingClosureRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public MemoraClosureRequest $closureRequest
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $closureRequestService = app(ClosureRequestService::class);
        $url = $closureRequestService->getPublicUrl($this->closureRequest->token);
        $proofing = $this->closureRequest->proofing;
        $media = $this->closureRequest->media;

        return (new MailMessage)
            ->subject('Closure Request for ' . $proofing->name)
            ->line('A closure request has been submitted for a revision in your proofing.')
            ->line('**Proofing:** ' . $proofing->name)
            ->line('**Media:** ' . ($media->file->filename ?? 'Media item'))
            ->line('Please review the comments and action items, then confirm if all discussed items are complete.')
            ->action('Review Closure Request', $url)
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'closure_request_uuid' => $this->closureRequest->uuid,
            'proofing_name' => $this->closureRequest->proofing->name,
        ];
    }
}
