<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraClosureRequest;
use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Support\MemoraFrontendUrls;
use App\Notifications\ProofingClosureApprovedNotification;
use App\Notifications\ProofingClosureRequestedNotification;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ClosureRequestService
{
    public function create(string $proofingId, string $mediaId, array $todos, string $userId): MemoraClosureRequest
    {
        $proofing = MemoraProofing::findOrFail($proofingId);
        $media = MemoraMedia::findOrFail($mediaId);

        // Verify user owns the proofing
        if ($proofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this proofing');
        }

        // Verify media belongs to proofing
        $mediaSet = $media->mediaSet;
        if (! $mediaSet || $mediaSet->proof_uuid !== $proofing->uuid) {
            throw new \Exception('Media does not belong to this proofing');
        }

        // Block closure request if media is already approved/completed
        if ($media->is_completed) {
            throw new \Exception('Cannot create closure request for approved media');
        }

        // Block closure request if media is rejected
        if ($media->is_rejected) {
            throw new \Exception('Cannot create closure request for rejected media');
        }

        // Check if there's already a pending closure request for this media
        $existingPendingRequest = MemoraClosureRequest::where('media_uuid', $mediaId)
            ->where('status', 'pending')
            ->first();

        if ($existingPendingRequest) {
            throw new \Exception('A closure request is already pending for this media. Please wait for the client to respond or cancel the existing request.');
        }

        $closureRequest = MemoraClosureRequest::create([
            'proofing_uuid' => $proofing->uuid,
            'media_uuid' => $media->uuid,
            'user_uuid' => $userId,
            'todos' => $todos,
            'status' => 'pending',
        ]);

        // Send email notification to primary email
        $primaryEmail = $proofing->primary_email;
        if ($primaryEmail) {
            try {
                Notification::route('mail', $primaryEmail)
                    ->notify(new ProofingClosureRequestedNotification($closureRequest));

                // Log activity for closure request email notification
                try {
                    app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                        'notification_sent',
                        $closureRequest,
                        'Proofing closure request email sent',
                        [
                            'channel' => 'email',
                            'notification' => 'ProofingClosureRequestedNotification',
                            'recipient_email' => $primaryEmail,
                            'proofing_uuid' => $proofing->uuid,
                            'media_uuid' => $media->uuid,
                        ]
                    );
                } catch (\Throwable $logException) {
                    Log::error('Failed to log proofing closure request notification activity', [
                        'closure_request_uuid' => $closureRequest->uuid ?? null,
                        'email' => $primaryEmail,
                        'error' => $logException->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send closure request notification', [
                    'closure_request_uuid' => $closureRequest->uuid,
                    'email' => $primaryEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // In-app notification for the creative (proofing owner)
        try {
            $notificationService = app(NotificationService::class);
            $actionUrl = MemoraFrontendUrls::proofingDetailPath($proofing->uuid, $proofing->project_uuid);
            $notificationService->create(
                $userId,
                'memora',
                'proofing_closure_requested',
                'Closure request sent',
                "Closure request sent for media in proofing \"{$proofing->name}\". The client will be notified by email.",
                null,
                null,
                $actionUrl,
                [
                    'closure_request_uuid' => $closureRequest->uuid,
                    'proofing_uuid' => $proofing->uuid,
                    'media_uuid' => $media->uuid,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to create in-app notification for closure request', [
                'closure_request_uuid' => $closureRequest->uuid ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        return $closureRequest;
    }

    /**
     * Resend closure request email to primary email only (authenticated - creative only).
     */
    public function resendNotification(string $closureRequestUuid, string $userId): void
    {
        $closureRequest = MemoraClosureRequest::with('proofing')->findOrFail($closureRequestUuid);
        $proofing = $closureRequest->proofing;

        if ($proofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this closure request');
        }

        if ($closureRequest->status !== 'pending') {
            throw new \Exception('Can only resend notification for pending closure requests');
        }

        $primaryEmail = $proofing->primary_email;
        if (! $primaryEmail) {
            return;
        }

        try {
            Notification::route('mail', $primaryEmail)
                ->notify(new ProofingClosureRequestedNotification($closureRequest));

            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                    'notification_sent',
                    $closureRequest,
                    'Proofing closure request email resent',
                    [
                        'channel' => 'email',
                        'notification' => 'ProofingClosureRequestedNotification',
                        'recipient_email' => $primaryEmail,
                        'proofing_uuid' => $proofing->uuid,
                        'media_uuid' => $closureRequest->media_uuid,
                    ]
                );
            } catch (\Throwable $logException) {
                Log::error('Failed to log closure request resend activity', [
                    'closure_request_uuid' => $closureRequest->uuid ?? null,
                    'email' => $primaryEmail,
                    'error' => $logException->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to resend closure request notification', [
                'closure_request_uuid' => $closureRequest->uuid,
                'email' => $primaryEmail,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a pending closure request (authenticated - creative only).
     * Use when the client is unresponsive so you can send a new request or continue editing.
     */
    public function cancel(string $closureRequestUuid, string $userId): MemoraClosureRequest
    {
        $closureRequest = MemoraClosureRequest::with('proofing')->findOrFail($closureRequestUuid);
        if ($closureRequest->proofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this closure request');
        }
        if ($closureRequest->status !== 'pending') {
            throw new \Exception('Can only cancel pending closure requests');
        }
        $closureRequest->update(['status' => 'cancelled']);

        return $closureRequest;
    }

    public function findByToken(string $token): ?MemoraClosureRequest
    {
        return MemoraClosureRequest::where('token', $token)
            ->with(['proofing', 'media.file', 'user'])
            ->first();
    }

    public function approve(string $token, string $email): MemoraClosureRequest
    {
        $closureRequest = $this->findByToken($token);

        if (! $closureRequest) {
            throw new \Exception('Closure request not found');
        }

        if ($closureRequest->status !== 'pending') {
            throw new \Exception('Closure request has already been processed');
        }

        // Verify email matches proofing's primary email or allowed emails
        $proofing = $closureRequest->proofing;
        $normalizedEmail = strtolower(trim($email));
        $normalizedPrimary = $proofing->primary_email ? strtolower(trim($proofing->primary_email)) : null;
        $normalizedAllowed = array_map(fn ($e) => strtolower(trim($e)), $proofing->allowed_emails ?? []);

        // Prevent creative (proofing owner) from approving/rejecting
        $creativeUser = $closureRequest->user;
        if ($creativeUser && strtolower(trim($creativeUser->email)) === $normalizedEmail) {
            throw new \Exception('Creatives cannot approve or reject their own closure requests');
        }

        if ($normalizedPrimary !== $normalizedEmail && ! in_array($normalizedEmail, $normalizedAllowed)) {
            throw new \Exception('Email does not match authorized email for this proofing');
        }

        // Block approval if media is already approved/completed
        if ($closureRequest->media->is_completed) {
            throw new \Exception('Cannot approve closure request for already approved media');
        }

        // Block approval if media is rejected
        if ($closureRequest->media->is_rejected) {
            throw new \Exception('Cannot approve closure request for rejected media');
        }

        // Update closure request and mark media ready for revision in a transaction
        return DB::transaction(function () use ($closureRequest, $email) {
            $closureRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_email' => $email,
            ]);

            // Mark media as ready for revision instead of completed
            $closureRequest->media->update([
                'is_ready_for_revision' => true,
            ]);

            // Log activity for closure request approved
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'closure_request_approved',
                subject: $closureRequest->media,
                description: "Closure request approved for media by {$email}.",
                properties: [
                    'closure_request_uuid' => $closureRequest->uuid,
                    'proofing_uuid' => $closureRequest->proofing_uuid,
                    'media_uuid' => $closureRequest->media_uuid,
                    'approved_by_email' => $email,
                ],
                causer: $closureRequest->user
            );

            // Send email notification to creative (outside transaction - don't rollback on email failure)
            try {
                Notification::route('mail', $closureRequest->user->email)
                    ->notify(new ProofingClosureApprovedNotification($closureRequest->fresh()));

                // Log activity for closure approved email notification
                try {
                    app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                        'notification_sent',
                        $closureRequest,
                        'Proofing closure approved email sent',
                        [
                            'channel' => 'email',
                            'notification' => 'ProofingClosureApprovedNotification',
                            'recipient_email' => $closureRequest->user->email,
                            'proofing_uuid' => $closureRequest->proofing_uuid,
                            'media_uuid' => $closureRequest->media_uuid,
                        ]
                    );
                } catch (\Throwable $logException) {
                    Log::error('Failed to log proofing closure approved notification activity', [
                        'closure_request_uuid' => $closureRequest->uuid ?? null,
                        'email' => $closureRequest->user->email ?? null,
                        'error' => $logException->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send closure approval notification', [
                    'closure_request_uuid' => $closureRequest->uuid,
                    'email' => $closureRequest->user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($closureRequest->user_uuid) {
                try {
                    $proofing = $closureRequest->proofing;
                    $actionUrl = MemoraFrontendUrls::proofingDetailPath($proofing->uuid, $proofing->project_uuid);
                    app(NotificationService::class)->create(
                        $closureRequest->user_uuid,
                        'memora',
                        'proofing_closure_approved',
                        'Closure request approved',
                        "The client approved the closure request for media in proofing \"{$proofing->name}\". You can upload a revision.",
                        null,
                        null,
                        $actionUrl,
                        [
                            'closure_request_uuid' => $closureRequest->uuid,
                            'proofing_uuid' => $proofing->uuid,
                            'media_uuid' => $closureRequest->media_uuid,
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::error('Failed to create in-app notification for closure approved', [
                        'closure_request_uuid' => $closureRequest->uuid ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $closureRequest->fresh();
        });
    }

    public function reject(string $token, string $email, ?string $reason = null): MemoraClosureRequest
    {
        $closureRequest = $this->findByToken($token);

        if (! $closureRequest) {
            throw new \Exception('Closure request not found');
        }

        if ($closureRequest->status !== 'pending') {
            throw new \Exception('Closure request has already been processed');
        }

        // Verify email matches proofing's primary email or allowed emails
        $proofing = $closureRequest->proofing;
        $normalizedEmail = strtolower(trim($email));
        $normalizedPrimary = $proofing->primary_email ? strtolower(trim($proofing->primary_email)) : null;
        $normalizedAllowed = array_map(fn ($e) => strtolower(trim($e)), $proofing->allowed_emails ?? []);

        // Prevent creative (proofing owner) from approving/rejecting
        $creativeUser = $closureRequest->user;
        if ($creativeUser && strtolower(trim($creativeUser->email)) === $normalizedEmail) {
            throw new \Exception('Creatives cannot approve or reject their own closure requests');
        }

        if ($normalizedPrimary !== $normalizedEmail && ! in_array($normalizedEmail, $normalizedAllowed)) {
            throw new \Exception('Email does not match authorized email for this proofing');
        }

        $closureRequest->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejection_reason' => $reason,
            'rejected_by_email' => $email,
        ]);

        // Log activity for closure request rejected
        app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
            action: 'closure_request_rejected',
            subject: $closureRequest->media,
            description: "Closure request rejected for media by {$email}.",
            properties: [
                'closure_request_uuid' => $closureRequest->uuid,
                'proofing_uuid' => $closureRequest->proofing_uuid,
                'media_uuid' => $closureRequest->media_uuid,
                'rejected_by_email' => $email,
                'rejection_reason' => $reason,
            ],
            causer: $closureRequest->user
        );

        // Send email notification to creative
        try {
            Notification::route('mail', $closureRequest->user->email)
                ->notify(new \App\Notifications\ProofingClosureRejectedNotification($closureRequest, $reason));

            // Log activity for closure rejected email notification
            try {
                app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                    'notification_sent',
                    $closureRequest,
                    'Proofing closure rejected email sent',
                    [
                        'channel' => 'email',
                        'notification' => 'ProofingClosureRejectedNotification',
                        'recipient_email' => $closureRequest->user->email,
                        'proofing_uuid' => $closureRequest->proofing_uuid,
                        'media_uuid' => $closureRequest->media_uuid,
                    ]
                );
            } catch (\Throwable $logException) {
                Log::error('Failed to log proofing closure rejected notification activity', [
                    'closure_request_uuid' => $closureRequest->uuid ?? null,
                    'email' => $closureRequest->user->email ?? null,
                    'error' => $logException->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send closure rejection notification', [
                'closure_request_uuid' => $closureRequest->uuid,
                'email' => $closureRequest->user->email,
                'error' => $e->getMessage(),
            ]);
        }

        if ($closureRequest->user_uuid) {
            try {
                $proofing = $closureRequest->proofing;
                $actionUrl = MemoraFrontendUrls::proofingDetailPath($proofing->uuid, $proofing->project_uuid);
                $message = "The client rejected the closure request for media in proofing \"{$proofing->name}\".";
                if ($reason) {
                    $message .= ' Reason: '.$reason;
                }
                app(NotificationService::class)->create(
                    $closureRequest->user_uuid,
                    'memora',
                    'proofing_closure_rejected',
                    'Closure request rejected',
                    $message,
                    null,
                    null,
                    $actionUrl,
                    [
                        'closure_request_uuid' => $closureRequest->uuid,
                        'proofing_uuid' => $proofing->uuid,
                        'media_uuid' => $closureRequest->media_uuid,
                        'rejection_reason' => $reason,
                    ]
                );
            } catch (\Throwable $e) {
                Log::error('Failed to create in-app notification for closure rejected', [
                    'closure_request_uuid' => $closureRequest->uuid ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $closureRequest->fresh();
    }

    public function getMediaComments(string $mediaUuid): array
    {
        $media = MemoraMedia::with(['feedback' => function ($query) {
            $query->whereNull('parent_uuid')
                ->with(['replies'])
                ->orderBy('created_at', 'asc');
        }])->findOrFail($mediaUuid);

        $mapFeedback = function ($feedback) use (&$mapFeedback) {
            $feedback->loadNestedReplies();
            return [
                'id' => $feedback->uuid,
                'content' => $feedback->content,
                'created_by' => $feedback->created_by,
                'created_at' => $feedback->created_at,
                'replies' => $feedback->replies->map(fn ($reply) => $mapFeedback($reply))->values()->toArray(),
            ];
        };

        return $media->feedback->map(fn ($f) => $mapFeedback($f))->toArray();
    }

    public function getPublicUrl(string $token): string
    {
        return MemoraFrontendUrls::closureRequestFullUrl($token);
    }

    public function getByMedia(string $mediaId, string $userId): array
    {
        $media = MemoraMedia::findOrFail($mediaId);

        // Verify user owns the media (through proofing)
        $mediaSet = $media->mediaSet;
        if (! $mediaSet) {
            throw new \Exception('Media does not belong to any set');
        }

        $proofing = $mediaSet->proofing;
        if (! $proofing || $proofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this media');
        }

        $closureRequests = MemoraClosureRequest::where('media_uuid', $mediaId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $closureRequests->map(function ($request) {
            return [
                'uuid' => $request->uuid,
                'token' => $request->token,
                'status' => $request->status,
                'todos' => $request->todos,
                'rejection_reason' => $request->rejection_reason,
                'rejected_by_email' => $request->rejected_by_email,
                'approved_by_email' => $request->approved_by_email,
                'created_at' => $request->created_at,
                'approved_at' => $request->approved_at,
                'rejected_at' => $request->rejected_at,
                'public_url' => $this->getPublicUrl($request->token),
            ];
        })->toArray();
    }
}
