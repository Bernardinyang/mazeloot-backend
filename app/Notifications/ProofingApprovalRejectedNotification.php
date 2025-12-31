<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraProofingApprovalRequest;
use App\Domains\Memora\Services\ProofingApprovalRequestService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProofingApprovalRejectedNotification extends Notification implements ShouldQueue
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

        $mail = (new MailMessage)
            ->subject('Approval Request Rejected - '.$proofing->name)
            ->line('Your approval request has been rejected by the client.')
            ->line('**Proofing:** '.$proofing->name)
            ->line('**Media:** '.($media->file->filename ?? 'Media item'));

        if ($this->approvalRequest->rejection_reason) {
            $mail->line('**Rejection Reason:** '.$this->approvalRequest->rejection_reason);
        }

        $mail->line('Please review the feedback and contact the client if you need further clarification.')
            ->action('View Approval Request', $url);

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
            'approval_request_uuid' => $this->approvalRequest->uuid,
            'proofing_name' => $this->approvalRequest->proofing->name,
            'rejection_reason' => $this->approvalRequest->rejection_reason,
        ];
    }
}
