<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraProofingApprovalRequest;
use App\Domains\Memora\Services\ProofingApprovalRequestService;
use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ProofingApprovalRequestedNotification extends Notification implements ShouldQueue
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
        $maxRevisions = $proofing->max_revisions ?? 5;

        return MailMessage::withLogo()
            ->subject('Approval Request for '.$proofing->name.' - Revision Limit Exceeded')
            ->line('An approval request has been submitted for a media item in your proofing.')
            ->line('**Proofing:** '.$proofing->name)
            ->line('**Media:** '.($media->file->filename ?? 'Media item'))
            ->line('**Reason:** The maximum revision limit ('.$maxRevisions.' revisions) has been reached. Approval is required to proceed.')
            ->line($this->approvalRequest->message ? '**Message:** '.$this->approvalRequest->message : '')
            ->action('Review Approval Request', $url)
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
