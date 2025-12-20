<?php

namespace App\Jobs;

use App\Domains\Memora\Services\SelectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoDeleteSelectionsJob implements ShouldQueue
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
    public function __construct()
    {
        $this->onQueue('auto-deletion');
    }

    /**
     * Execute the job.
     */
    public function handle(SelectionService $selectionService): void
    {
        try {
            $result = $selectionService->autoDeleteExpiredSelections();
            
            Log::info(
                "Auto-deleted {$result['selected_media_deleted']} selected media item(s)"
            );
        } catch (\Exception $e) {
            Log::error("Failed to auto-delete selections: " . $e->getMessage());
            throw $e;
        }
    }
}
