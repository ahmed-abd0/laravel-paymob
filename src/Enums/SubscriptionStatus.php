<?php

namespace Paymob\Laravel\Enums;

enum SubscriptionStatus: string
{
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case SUSPENDED = 'suspended';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';

    public static function fromPaymob(?string $state): self
    {
        return match (strtolower((string) $state)) {
            'active', 'activated' => self::ACTIVE,
            'suspended', 'paused' => self::SUSPENDED,
            'cancelled', 'canceled' => self::CANCELED,
            'past_due', 'failed', 'payment_failed' => self::PAST_DUE,
            'expired', 'ended' => self::EXPIRED,
            default => self::INCOMPLETE
        };
    }
}
