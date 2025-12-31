<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Services\ClosureRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProofingClosureRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public MemoraClosureRequest $closureRequest,
        public ?string $reason = null
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

        $mail = (new MailMessage)
            ->subject('Closure Request Rejected - '.$proofing->name)
            ->line('Your closure request has been rejected by the client.')
            ->line('**Proofing:** '.$proofing->name)
            ->line('**Media:** '.($media->file->filename ?? 'Media item'));

        if ($this->reason) {
            $mail->line('**Rejection Reason:** '.$this->reason);
        }

        $mail->line('Please review the feedback, make necessary changes, and submit a new closure request when ready.')
            ->action('View Closure Request', $url);

        return $mail;
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
            'rejection_reason' => $this->reason,
        ];
    }
}
