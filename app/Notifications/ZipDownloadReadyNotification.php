<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraCollection;
use App\Support\Mail\MailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ZipDownloadReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MemoraCollection $collection,
        public string $zipFilename,
        public string $token
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectId = $this->collection->project_uuid ?? 'standalone';
        $downloadUrl = config('app.frontend_url', config('app.url'))."/p/{$projectId}/collection/download?token={$this->token}&collectionId={$this->collection->uuid}";

        return MailMessage::withLogo()
            ->subject('Your Photos Are Ready to Download')
            ->line('Your photos from **'.$this->collection->name.'** are ready to download!')
            ->line('Click the link below to download your ZIP file:')
            ->action('Download Photos', $downloadUrl)
            ->line('The download link will be available for 24 hours.')
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'collection_id' => $this->collection->uuid,
            'collection_name' => $this->collection->name,
            'zip_filename' => $this->zipFilename,
            'token' => $this->token,
        ];
    }
}
