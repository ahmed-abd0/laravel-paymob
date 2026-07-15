<?php

namespace Paymob\Laravel\Enums;

enum PlanType: string
{
    case RENT = 'rent';
    case INSTALLMENT = 'installment';
    case PURCHASE = 'purchase';
    case BUNDLE = 'bundle';
    case MERCHANT_SUBSCRIPTION = 'merchant_subscription';
    case OTHER = 'other';
}
