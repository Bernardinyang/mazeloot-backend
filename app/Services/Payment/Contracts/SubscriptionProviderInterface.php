<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTOs\SubscriptionResult;

interface SubscriptionProviderInterface
{
    /**
     * Create a subscription
     *
     * @param  array  $subscriptionData  Plan, customer, billing cycle, etc.
     */
    public function createSubscription(array $subscriptionData): SubscriptionResult;

    /**
     * Cancel a subscription
     */
    public function cancelSubscription(string $subscriptionId): SubscriptionResult;

    /**
     * Update a subscription
     */
    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult;

    /**
     * Get subscription status
     */
    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult;
}
