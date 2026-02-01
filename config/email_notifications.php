<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Notification Events
    |--------------------------------------------------------------------------
    |
    | All notification types that can trigger emails. Used for UI, sending,
    | and user preferences. Default true = enabled for new users.
    |
    */

    'events' => [
        // Collection Phase
        'collection_published' => [
            'label' => 'Collection Published',
            'description' => 'Receive an email when a collection is published',
            'default' => true,
            'group' => 'collection',
        ],
        'collection_shared' => [
            'label' => 'Collection Shared',
            'description' => 'Receive an email when a collection is shared',
            'default' => true,
            'group' => 'collection',
        ],
        'collection_download' => [
            'label' => 'Collection Download',
            'description' => 'Receive an email when clients download from collections',
            'default' => true,
            'group' => 'collection',
        ],
        'collection_email_registration' => [
            'label' => 'Collection Email Registration',
            'description' => 'Receive an email when clients register their email to access collections',
            'default' => true,
            'group' => 'collection',
        ],
        'collection_favorite' => [
            'label' => 'Collection Favorite',
            'description' => 'Receive an email when clients favorite photos in collections',
            'default' => true,
            'group' => 'collection',
        ],
        'collection_view' => [
            'label' => 'Collection View',
            'description' => 'Receive an email when collections are viewed',
            'default' => true,
            'group' => 'collection',
        ],

        // Selection Phase
        'selection_published' => [
            'label' => 'Selection Published',
            'description' => 'Receive an email when a selection is published',
            'default' => true,
            'group' => 'selection',
        ],
        'selection_completed' => [
            'label' => 'Selection Completed',
            'description' => 'Receive an email when a selection is completed',
            'default' => true,
            'group' => 'selection',
        ],
        'selection_access' => [
            'label' => 'Selection Access',
            'description' => 'Receive an email when clients access selections',
            'default' => true,
            'group' => 'selection',
        ],
        'selection_limit_reached' => [
            'label' => 'Selection Limit Reached',
            'description' => 'Receive an email when selection limit is reached',
            'default' => true,
            'group' => 'selection',
        ],

        // Proofing Phase
        'proofing_published' => [
            'label' => 'Proofing Published',
            'description' => 'Receive an email when proofing is published',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_completed' => [
            'label' => 'Proofing Completed',
            'description' => 'Receive an email when proofing is completed',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_comment' => [
            'label' => 'Proofing Comment',
            'description' => 'Receive an email when comments are added to proofing',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_revision_uploaded' => [
            'label' => 'Proofing Revision Uploaded',
            'description' => 'Receive an email when new revisions are uploaded',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_approval_requested' => [
            'label' => 'Proofing Approval Requested',
            'description' => 'Receive an email when approval requests are submitted',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_approval_approved' => [
            'label' => 'Proofing Approval Approved',
            'description' => 'Receive an email when approval requests are approved',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_approval_rejected' => [
            'label' => 'Proofing Approval Rejected',
            'description' => 'Receive an email when approval requests are rejected',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_closure_requested' => [
            'label' => 'Proofing Closure Requested',
            'description' => 'Receive an email when closure requests are submitted',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_closure_approved' => [
            'label' => 'Proofing Closure Approved',
            'description' => 'Receive an email when closure requests are approved',
            'default' => true,
            'group' => 'proofing',
        ],
        'proofing_closure_rejected' => [
            'label' => 'Proofing Closure Rejected',
            'description' => 'Receive an email when closure requests are rejected',
            'default' => true,
            'group' => 'proofing',
        ],

        // Project
        'project_created' => [
            'label' => 'Project Created',
            'description' => 'Receive an email when a new project is created',
            'default' => true,
            'group' => 'project',
        ],
        'project_updated' => [
            'label' => 'Project Updated',
            'description' => 'Receive an email when a project is updated',
            'default' => true,
            'group' => 'project',
        ],

        // Media & Feedback
        'media_feedback' => [
            'label' => 'Media Feedback',
            'description' => 'Receive an email when clients provide feedback on media',
            'default' => true,
            'group' => 'media',
        ],
        'media_uploaded' => [
            'label' => 'Media Uploaded',
            'description' => 'Receive an email when media is uploaded',
            'default' => true,
            'group' => 'media',
        ],

        // Payment & Plan
        'subscription_activated' => [
            'label' => 'Subscription Activated',
            'description' => 'Receive an email when your subscription is activated or upgraded',
            'default' => true,
            'group' => 'payment',
        ],
        'subscription_renewed' => [
            'label' => 'Subscription Renewed',
            'description' => 'Receive an email when your subscription renews',
            'default' => true,
            'group' => 'payment',
        ],
        'subscription_cancelled' => [
            'label' => 'Subscription Cancelled',
            'description' => 'Receive an email when your subscription is cancelled',
            'default' => true,
            'group' => 'payment',
        ],
        'payment_failed' => [
            'label' => 'Payment Failed',
            'description' => 'Receive an email when a payment fails (action required)',
            'default' => true,
            'group' => 'payment',
            'critical' => true,
        ],

        // General
        'weekly_summary' => [
            'label' => 'Weekly Summary',
            'description' => 'Receive a weekly summary of your activity',
            'default' => true,
            'group' => 'general',
        ],
        'monthly_summary' => [
            'label' => 'Monthly Summary',
            'description' => 'Receive a monthly summary of your activity',
            'default' => true,
            'group' => 'general',
        ],
    ],

    'groups' => [
        'collection' => 'Collection',
        'selection' => 'Selection',
        'proofing' => 'Proofing',
        'project' => 'Project',
        'media' => 'Media & Feedback',
        'payment' => 'Payment & Plan',
        'general' => 'General',
    ],
];
