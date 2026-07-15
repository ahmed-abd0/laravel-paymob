<?php

namespace Paymob\Laravel\Enums;

enum TransactionStatus: string
{
    case PENDING = 'pending';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case VOIDED = 'voided';
    case CAPTURED = 'captured';

    public static function fromPayload(array $payload): self
    {
        if ((bool) ($payload['is_refunded'] ?? false)) return self::REFUNDED;
        if ((bool) ($payload['is_voided'] ?? false)) return self::VOIDED;
        if ((bool) ($payload['is_capture'] ?? false) || (bool) ($payload['is_captured'] ?? false)) return self::CAPTURED;
        if ((bool) ($payload['pending'] ?? false)) return self::PENDING;
        return (bool) ($payload['success'] ?? false) ? self::SUCCEEDED : self::FAILED;
    }
}
