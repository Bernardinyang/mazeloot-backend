<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\DTOs\SubscriptionResult;

interface SubscriptionProviderInterface
{
    /**
     * Create a subscription
     *
     * @param array $subscriptionData Plan, customer, billing cycle, etc.
     * @return SubscriptionResult
     */
    public function createSubscription(array $subscriptionData): SubscriptionResult;

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId
     * @return SubscriptionResult
     */
    public function cancelSubscription(string $subscriptionId): SubscriptionResult;

    /**
     * Update a subscription
     *
     * @param string $subscriptionId
     * @param array $updates
     * @return SubscriptionResult
     */
    public function updateSubscription(string $subscriptionId, array $updates): SubscriptionResult;

    /**
     * Get subscription status
     *
     * @param string $subscriptionId
     * @return SubscriptionResult
     */
    public function getSubscriptionStatus(string $subscriptionId): SubscriptionResult;
}
