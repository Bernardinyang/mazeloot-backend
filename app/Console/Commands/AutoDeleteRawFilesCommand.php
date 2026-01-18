<?php

namespace App\Console\Commands;

use App\Domains\Memora\Services\RawFileService;
use Illuminate\Console\Command;

class AutoDeleteRawFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'raw-files:auto-delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically delete unselected media from raw files that have passed their auto_delete_date';

    /**
     * Execute the console command.
     */
    public function handle(RawFileService $rawFileService): int
    {
        $this->info('Starting auto-deletion of unselected media from expired raw files...');

        try {
            $result = $rawFileService->autoDeleteExpiredRawFiles();

            if ($result['unselected_media_deleted'] > 0) {
                $this->info(
                    "Successfully deleted {$result['unselected_media_deleted']} unselected media item(s). ".
                    'Raw files remain intact.'
                );
            } else {
                $this->info('No expired raw files found.');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to auto-delete raw files: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
