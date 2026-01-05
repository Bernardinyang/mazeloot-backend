<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraProofingApprovalRequest;
use App\Domains\Memora\Services\ProofingApprovalRequestService;
use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProofingApprovalApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public MemoraProofingApprovalRequest $approvalRequest
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
        $approvalRequestService = app(ProofingApprovalRequestService::class);
        $url = $approvalRequestService->getPublicUrl($this->approvalRequest->token);
        $proofing = $this->approvalRequest->proofing;
        $media = $this->approvalRequest->media;

        return MailMessage::withLogo()
            ->subject('Approval Request Approved - '.$proofing->name)
            ->line('Your approval request has been approved by the client.')
            ->line('**Proofing:** '.$proofing->name)
            ->line('**Media:** '.($media->file->filename ?? 'Media item'))
            ->line('You can now proceed with additional revisions as needed.')
            ->action('View Approval Request', $url)
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
            'approval_request_uuid' => $this->approvalRequest->uuid,
            'proofing_name' => $this->approvalRequest->proofing->name,
        ];
    }
}
