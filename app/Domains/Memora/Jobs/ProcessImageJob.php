<?php

namespace App\Domains\Memora\Jobs;

use App\Domains\Memora\Services\MediaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $mediaId,
        public array $options = []
    ) {
        // Set queue based on priority
        $this->onQueue($options['queue'] ?? 'images');
    }

    /**
     * Execute the job.
     */
    public function handle(MediaService $mediaService): void
    {
        $mediaService->processImage($this->mediaId, $this->options);
    }
}

