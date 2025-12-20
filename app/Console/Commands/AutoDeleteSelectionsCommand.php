<?php

namespace App\Console\Commands;

use App\Domains\Memora\Services\SelectionService;
use Illuminate\Console\Command;

class AutoDeleteSelectionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selections:auto-delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically delete selections that have passed their auto_delete_date';

    /**
     * Execute the console command.
     */
    public function handle(SelectionService $selectionService): int
    {
        $this->info('Starting auto-deletion of selected media from expired selections...');

        try {
            $result = $selectionService->autoDeleteExpiredSelections();

            if ($result['selected_media_deleted'] > 0) {
                $this->info(
                    "Successfully deleted {$result['selected_media_deleted']} selected media item(s). " .
                    "Selections remain intact."
                );
            } else {
                $this->info('No expired selections found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to auto-delete selections: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
