<?php

namespace Paymob\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Paymob\Laravel\Resources\Intentions intentions()
 * @method static \Paymob\Laravel\Resources\SubscriptionPlans subscriptionPlans()
 * @method static \Paymob\Laravel\Resources\Subscriptions subscriptions()
 * @method static \Paymob\Laravel\Resources\Transactions transactions()
 * @method static \Paymob\Laravel\Resources\Payments payments()
 * @method static \Paymob\Laravel\Resources\QuickLinks quickLinks()
 * @method static \Paymob\Laravel\Resources\SavedCards savedCards()
 * @method static \Paymob\Laravel\Billing\PlanManager plans()
 * @method static \Paymob\Laravel\Billing\SubscriptionManager billing()
 */
final class Paymob extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Paymob\Laravel\Paymob::class;
    }
}
