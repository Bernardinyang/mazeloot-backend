<?php

namespace App\Jobs;

use App\Services\ActivityLog\ActivityLogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $action,
        public ?string $subjectType = null,
        public ?string $subjectUuid = null,
        public ?string $description = null,
        public ?array $properties = null,
        public ?string $causerType = null,
        public ?string $causerUuid = null,
        public ?string $userUuid = null,
        public ?string $route = null,
        public ?string $method = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null
    ) {
        $this->onQueue('activity-log');
    }

    /**
     * Execute the job.
     */
    public function handle(ActivityLogService $activityLogService): void
    {
        $activityLogService->processQueuedLog(
            action: $this->action,
            subjectType: $this->subjectType,
            subjectUuid: $this->subjectUuid,
            description: $this->description,
            properties: $this->properties,
            causerType: $this->causerType,
            causerUuid: $this->causerUuid,
            userUuid: $this->userUuid,
            route: $this->route,
            method: $this->method,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent
        );
    }
}

