<?php

namespace Paymob\Laravel\Enums;

enum WebhookType: string
{
    case TRANSACTION = 'transaction';
    case TOKEN = 'token';
    case SUBSCRIPTION = 'subscription';
    case UNKNOWN = 'unknown';

    public static function detect(array $payload): self
    {
        $type = strtoupper((string) ($payload['type'] ?? ''));
        if ($type === 'TRANSACTION') return self::TRANSACTION;
        if ($type === 'TOKEN') return self::TOKEN;
        if (isset($payload['subscription_id'], $payload['state']) || isset($payload['plan_id'], $payload['state'])) return self::SUBSCRIPTION;
        if (isset($payload['obj']['token'], $payload['obj']['masked_pan'])) return self::TOKEN;
        if (isset($payload['obj']['success']) || isset($payload['success'], $payload['amount_cents'])) return self::TRANSACTION;
        return self::UNKNOWN;
    }
}
