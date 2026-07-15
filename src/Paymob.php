<?php

namespace Paymob\Laravel;

use Paymob\Laravel\Billing\PlanManager;
use Paymob\Laravel\Billing\SubscriptionManager;
use Paymob\Laravel\Resources\Intentions;
use Paymob\Laravel\Resources\Payments;
use Paymob\Laravel\Resources\QuickLinks;
use Paymob\Laravel\Resources\SavedCards;
use Paymob\Laravel\Resources\SubscriptionPlans;
use Paymob\Laravel\Resources\Subscriptions;
use Paymob\Laravel\Resources\Transactions;

final class Paymob
{
    public function __construct(
        private readonly Intentions $intentions,
        private readonly SubscriptionPlans $subscriptionPlans,
        private readonly Subscriptions $subscriptions,
        private readonly Transactions $transactions,
        private readonly Payments $payments,
        private readonly QuickLinks $quickLinks,
        private readonly SavedCards $savedCards,
        private readonly PlanManager $planManager,
        private readonly SubscriptionManager $subscriptionManager
    ) {}

    public function intentions(): Intentions
    {
        return $this->intentions;
    }
    public function subscriptionPlans(): SubscriptionPlans
    {
        return $this->subscriptionPlans;
    }
    public function subscriptions(): Subscriptions
    {
        return $this->subscriptions;
    }
    public function transactions(): Transactions
    {
        return $this->transactions;
    }
    public function payments(): Payments
    {
        return $this->payments;
    }
    public function quickLinks(): QuickLinks
    {
        return $this->quickLinks;
    }
    public function savedCards(): SavedCards
    {
        return $this->savedCards;
    }
    public function plans(): PlanManager
    {
        return $this->planManager;
    }
    public function billing(): SubscriptionManager
    {
        return $this->subscriptionManager;
    }
}
