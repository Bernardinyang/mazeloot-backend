<?php

namespace App\Domains\Memora\Services;

use App\Domains\Memora\Models\MemoraMedia;
use App\Domains\Memora\Models\MemoraProofing;
use App\Domains\Memora\Models\MemoraProofingApprovalRequest;
use App\Notifications\ProofingApprovalApprovedNotification;
use App\Notifications\ProofingApprovalRejectedNotification;
use App\Notifications\ProofingApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ProofingApprovalRequestService
{
    public function __construct(
        private MediaService $mediaService
    ) {}

    public function create(string $proofingId, string $mediaId, ?string $message, string $userId): MemoraProofingApprovalRequest
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

        // Block approval request if media is already approved/completed
        if ($media->is_completed) {
            throw new \Exception('Cannot create approval request for approved media');
        }

        // Block approval request if media is rejected
        if ($media->is_rejected) {
            throw new \Exception('Cannot create approval request for rejected media');
        }

        // Verify revision limit is actually exceeded before allowing approval request
        $originalMediaUuid = $media->original_media_uuid ?? $media->uuid;
        $maxRevision = MemoraMedia::where(function ($query) use ($originalMediaUuid) {
            $query->where('original_media_uuid', $originalMediaUuid)
                ->orWhere('uuid', $originalMediaUuid);
        })->max('revision_number') ?? 0;

        $maxRevisions = $proofing->max_revisions ?? 5;
        if ($maxRevision < $maxRevisions) {
            throw new \Exception("Approval request can only be created when revision limit ({$maxRevisions}) is exceeded. Current revision: {$maxRevision}");
        }

        // Check if there's already a pending approval request for this media
        $existingPendingRequest = MemoraProofingApprovalRequest::where('media_uuid', $mediaId)
            ->where('status', 'pending')
            ->first();

        if ($existingPendingRequest) {
            throw new \Exception('An approval request is already pending for this media. Please wait for the client to respond or cancel the existing request.');
        }

        $approvalRequest = MemoraProofingApprovalRequest::create([
            'proofing_uuid' => $proofing->uuid,
            'media_uuid' => $media->uuid,
            'user_uuid' => $userId,
            'message' => $message,
            'status' => 'pending',
        ]);

        // Send email notification to primary email or allowed emails
        $primaryEmail = $proofing->primary_email;
        $allowedEmails = $proofing->allowed_emails ?? [];

        $emailsToNotify = [];
        if ($primaryEmail) {
            $emailsToNotify[] = $primaryEmail;
        }
        foreach ($allowedEmails as $email) {
            if ($email && ! in_array($email, $emailsToNotify)) {
                $emailsToNotify[] = $email;
            }
        }

        foreach ($emailsToNotify as $email) {
            try {
                Notification::route('mail', $email)
                    ->notify(new ProofingApprovalRequestedNotification($approvalRequest));

                // Log activity for approval request email notification
                try {
                    app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                        'notification_sent',
                        $approvalRequest,
                        'Proofing approval request email sent',
                        [
                            'channel' => 'email',
                            'notification' => 'ProofingApprovalRequestedNotification',
                            'recipient_email' => $email,
                            'proofing_uuid' => $proofing->uuid,
                            'media_uuid' => $media->uuid,
                        ]
                    );
                } catch (\Throwable $logException) {
                    Log::error('Failed to log proofing approval request notification activity', [
                        'approval_request_uuid' => $approvalRequest->uuid ?? null,
                        'email' => $email,
                        'error' => $logException->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send approval request notification', [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $approvalRequest;
    }

    public function findByToken(string $token): ?MemoraProofingApprovalRequest
    {
        return MemoraProofingApprovalRequest::where('token', $token)
            ->with(['proofing', 'media.file', 'user'])
            ->first();
    }

    public function approve(string $token, string $email): MemoraProofingApprovalRequest
    {
        $approvalRequest = $this->findByToken($token);

        if (! $approvalRequest) {
            throw new \Exception('Approval request not found');
        }

        if ($approvalRequest->status !== 'pending') {
            throw new \Exception('Approval request has already been processed');
        }

        // Verify email matches proofing's primary email or allowed emails
        $proofing = $approvalRequest->proofing;
        $normalizedEmail = strtolower(trim($email));
        $normalizedPrimary = $proofing->primary_email ? strtolower(trim($proofing->primary_email)) : null;
        $normalizedAllowed = array_map(fn ($e) => strtolower(trim($e)), $proofing->allowed_emails ?? []);

        // Prevent creative (proofing owner) from approving/rejecting
        $creativeUser = $approvalRequest->user;
        if ($creativeUser && strtolower(trim($creativeUser->email)) === $normalizedEmail) {
            throw new \Exception('Creatives cannot approve or reject their own approval requests');
        }

        if ($normalizedPrimary !== $normalizedEmail && ! in_array($normalizedEmail, $normalizedAllowed)) {
            throw new \Exception('Email does not match authorized email for this proofing');
        }

        // Block approval if media is already approved/completed
        if ($approvalRequest->media->is_completed) {
            throw new \Exception('Cannot approve approval request for already approved media');
        }

        // Block approval if media is rejected
        if ($approvalRequest->media->is_rejected) {
            throw new \Exception('Cannot approve approval request for rejected media');
        }

        // Update approval request and approve media in a transaction
        return DB::transaction(function () use ($approvalRequest, $email) {
            $approvalRequest->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_email' => $email,
            ]);

            // Approve the media
            try {
                $this->mediaService->markCompleted($approvalRequest->media_uuid, true);
            } catch (\Exception $e) {
                Log::error('Failed to approve media after approval request', [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'media_uuid' => $approvalRequest->media_uuid,
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to trigger rollback
            }

            // Log activity for approval request approved
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'approval_request_approved',
                subject: $approvalRequest->media,
                description: "Approval request approved for media by {$email}.",
                properties: [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'proofing_uuid' => $approvalRequest->proofing_uuid,
                    'media_uuid' => $approvalRequest->media_uuid,
                    'approved_by_email' => $email,
                ],
                causer: $approvalRequest->user
            );

            // Send email notification to creative (outside transaction - don't rollback on email failure)
            try {
                Notification::route('mail', $approvalRequest->user->email)
                    ->notify(new ProofingApprovalApprovedNotification($approvalRequest->fresh()));

                // Log activity for approval approved email notification
                try {
                    app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                        'notification_sent',
                        $approvalRequest,
                        'Proofing approval approved email sent',
                        [
                            'channel' => 'email',
                            'notification' => 'ProofingApprovalApprovedNotification',
                            'recipient_email' => $approvalRequest->user->email,
                            'proofing_uuid' => $approvalRequest->proofing_uuid,
                            'media_uuid' => $approvalRequest->media_uuid,
                        ]
                    );
                } catch (\Throwable $logException) {
                    Log::error('Failed to log proofing approval approved notification activity', [
                        'approval_request_uuid' => $approvalRequest->uuid ?? null,
                        'email' => $approvalRequest->user->email ?? null,
                        'error' => $logException->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send approval notification', [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'email' => $approvalRequest->user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            return $approvalRequest->fresh();
        });
    }

    public function reject(string $token, string $email, ?string $reason = null): MemoraProofingApprovalRequest
    {
        $approvalRequest = $this->findByToken($token);

        if (! $approvalRequest) {
            throw new \Exception('Approval request not found');
        }

        if ($approvalRequest->status !== 'pending') {
            throw new \Exception('Approval request has already been processed');
        }

        // Verify email matches proofing's primary email or allowed emails
        $proofing = $approvalRequest->proofing;
        $normalizedEmail = strtolower(trim($email));
        $normalizedPrimary = $proofing->primary_email ? strtolower(trim($proofing->primary_email)) : null;
        $normalizedAllowed = array_map(fn ($e) => strtolower(trim($e)), $proofing->allowed_emails ?? []);

        // Prevent creative (proofing owner) from approving/rejecting
        $creativeUser = $approvalRequest->user;
        if ($creativeUser && strtolower(trim($creativeUser->email)) === $normalizedEmail) {
            throw new \Exception('Creatives cannot approve or reject their own approval requests');
        }

        if ($normalizedPrimary !== $normalizedEmail && ! in_array($normalizedEmail, $normalizedAllowed)) {
            throw new \Exception('Email does not match authorized email for this proofing');
        }

        // Block rejection if media is already approved/completed
        if ($approvalRequest->media->is_completed) {
            throw new \Exception('Cannot reject approval request for already approved media');
        }

        // Update approval request and reject media in a transaction
        return DB::transaction(function () use ($approvalRequest, $email, $reason) {
            $approvalRequest->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by_email' => $email,
                'rejection_reason' => $reason,
            ]);

            // Mark the media as rejected
            try {
                $this->mediaService->markRejected($approvalRequest->media_uuid, true);
            } catch (\Exception $e) {
                Log::error('Failed to reject media after approval request rejection', [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'media_uuid' => $approvalRequest->media_uuid,
                    'error' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to trigger rollback
            }

            // Log activity for approval request rejected
            app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                action: 'approval_request_rejected',
                subject: $approvalRequest->media,
                description: "Approval request rejected for media by {$email}.",
                properties: [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'proofing_uuid' => $approvalRequest->proofing_uuid,
                    'media_uuid' => $approvalRequest->media_uuid,
                    'rejected_by_email' => $email,
                    'rejection_reason' => $reason,
                ],
                causer: $approvalRequest->user
            );

            // Send email notification to creative (outside transaction - don't rollback on email failure)
            try {
                Notification::route('mail', $approvalRequest->user->email)
                    ->notify(new ProofingApprovalRejectedNotification($approvalRequest->fresh()));

                // Log activity for approval rejected email notification
                try {
                    app(\App\Services\ActivityLog\ActivityLogService::class)->logQueued(
                        'notification_sent',
                        $approvalRequest,
                        'Proofing approval rejected email sent',
                        [
                            'channel' => 'email',
                            'notification' => 'ProofingApprovalRejectedNotification',
                            'recipient_email' => $approvalRequest->user->email,
                            'proofing_uuid' => $approvalRequest->proofing_uuid,
                            'media_uuid' => $approvalRequest->media_uuid,
                        ]
                    );
                } catch (\Throwable $logException) {
                    Log::error('Failed to log proofing approval rejected notification activity', [
                        'approval_request_uuid' => $approvalRequest->uuid ?? null,
                        'email' => $approvalRequest->user->email ?? null,
                        'error' => $logException->getMessage(),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Failed to send approval rejection notification', [
                    'approval_request_uuid' => $approvalRequest->uuid,
                    'email' => $approvalRequest->user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            return $approvalRequest->fresh();
        });
    }

    public function getByMedia(string $mediaId, string $userId): array
    {
        $media = MemoraMedia::findOrFail($mediaId);
        $mediaSet = $media->mediaSet;

        if (! $mediaSet) {
            throw new \Exception('Media does not belong to a proofing');
        }

        $proofing = MemoraProofing::findOrFail($mediaSet->proof_uuid);

        if ($proofing->user_uuid !== $userId) {
            throw new \Exception('Unauthorized: You do not own this proofing');
        }

        return MemoraProofingApprovalRequest::where('media_uuid', $mediaId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function getPublicUrl(string $token): string
    {
        return config('app.frontend_url').'/p/approval-request/'.$token;
    }
}
