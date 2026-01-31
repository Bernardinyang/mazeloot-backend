<?php

namespace App\Notifications;

use App\Domains\Memora\Models\MemoraCollection;
use App\Support\Mail\MailMessage;
use App\Support\MemoraFrontendUrls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CollectionDownloadedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public MemoraCollection $collection,
        public string $downloaderEmail,
        public int $mediaCount,
        public string $downloadSize
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $brandingDomain = MemoraFrontendUrls::getBrandingDomainForUser($this->collection->user_uuid);
        $domain = $brandingDomain ?? $this->collection->project_uuid ?? 'standalone';
        $collectionUrl = MemoraFrontendUrls::publicCollectionFullUrl($domain, $this->collection->uuid);

        $downloaderInfo = $this->downloaderEmail ? "by **{$this->downloaderEmail}**" : 'by a visitor';

        return MailMessage::withLogo()
            ->subject('Collection Downloaded: '.$this->collection->name)
            ->line("Your collection **{$this->collection->name}** has been downloaded {$downloaderInfo}.")
            ->line('**Download Details:**')
            ->line("- **Media Count:** {$this->mediaCount} ".($this->mediaCount === 1 ? 'photo' : 'photos'))
            ->line("- **Download Size:** {$this->downloadSize}")
            ->action('View Collection', $collectionUrl)
            ->line('Thank you for using our application!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'collection_id' => $this->collection->uuid,
            'collection_name' => $this->collection->name,
            'downloader_email' => $this->downloaderEmail,
            'media_count' => $this->mediaCount,
            'download_size' => $this->downloadSize,
        ];
    }
}
