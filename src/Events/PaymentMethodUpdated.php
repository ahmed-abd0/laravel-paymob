<?php

namespace Paymob\Laravel\Events;

use Paymob\Laravel\Models\PaymentMethod;

final class PaymentMethodUpdated
{
    public function __construct(public readonly PaymentMethod $paymentMethod) {}
}
