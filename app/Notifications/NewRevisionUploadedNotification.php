<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Support\Mail\MailMessage;
use App\Support\MemoraFrontendUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewRevisionUploadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MemoraProofing $proofing,
        public MemoraMedia $revisionMedia
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandingDomain = MemoraFrontendUrls::getBrandingDomainForUser($this->proofing->user_uuid);
        $url = MemoraFrontendUrls::publicProofingFullUrl(
            $this->proofing->uuid,
            $brandingDomain,
            $this->proofing->project_uuid
        );
        $mediaName = $this->revisionMedia->file->filename ?? 'Media item';
        $revisionNum = $this->revisionMedia->revision_number ?? 1;

        return MailMessage::withLogo()
            ->subject('New revision uploaded: '.$this->proofing->name)
            ->line('A new revision has been uploaded to your proofing.')
            ->line('**Proofing:** '.$this->proofing->name)
            ->line('**Media:** '.$mediaName.' (revision '.$revisionNum.')')
            ->action('View proofing', $url)
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'proofing_uuid' => $this->proofing->uuid,
            'media_uuid' => $this->revisionMedia->uuid,
            'revision_number' => $this->revisionMedia->revision_number,
        ];
    }
}
