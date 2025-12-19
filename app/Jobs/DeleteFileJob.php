<?php

namespace App\Jobs;

use App\Services\Upload\UploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteFileJob implements ShouldQueue
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
    public $backoff = 30;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $filePath,
        public ?array $additionalPaths = null
    ) {
        $this->onQueue('file-deletion');
    }

    /**
     * Execute the job.
     */
    public function handle(UploadService $uploadService): void
    {
        $uploadService->deleteFiles($this->filePath, $this->additionalPaths);
    }
}

