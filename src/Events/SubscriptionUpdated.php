<?php

namespace Paymob\Laravel\Events;

use Paymob\Laravel\Models\Subscription;

final class SubscriptionUpdated
{
    public function __construct(public readonly Subscription $subscription) {}
}
