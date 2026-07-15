<?php

namespace Paymob\Laravel\Webhooks;

use Paymob\Laravel\Exceptions\ConfigurationException;

final class SignatureVerifier
{
    private const TRANSACTION_KEYS = [
        'amount_cents',
        'created_at',
        'currency',
        'error_occured',
        'has_parent_transaction',
        'id',
        'integration_id',
        'is_3d_secure',
        'is_auth',
        'is_capture',
        'is_refunded',
        'is_standalone_payment',
        'is_voided',
        'order.id',
        'owner',
        'pending',
        'source_data.pan',
        'source_data.sub_type',
        'source_data.type',
        'success',
    ];

    private const TOKEN_KEYS = ['card_subtype', 'created_at', 'email', 'id', 'masked_pan', 'merchant_id', 'order_id', 'token'];

    public function transaction(array $payload, ?string $signature): bool
    {
        if (! config('paymob.webhooks.verify_transaction_hmac', true)) {
            return true;
        }

        return $this->verify($this->transactionValues($this->object($payload)), $signature);
    }

    public function token(array $payload, ?string $signature): bool
    {
        if (! config('paymob.webhooks.verify_transaction_hmac', true)) {
            return true;
        }
        $object = $this->object($payload);

        return $this->verify(array_map(fn (string $key) => $this->string($object[$key] ?? null), self::TOKEN_KEYS), $signature);
    }

    public function subscription(?string $providedSecret): bool
    {
        $expected = config('paymob.webhooks.subscription_secret');
        if (! $expected) {
            return ! config('paymob.webhooks.require_subscription_secret', true);
        }

        return is_string($providedSecret) && hash_equals($expected, $providedSecret);
    }

    private function transactionValues(array $object): array
    {
        return array_map(function (string $key) use ($object) {
            $fallbacks = match ($key) {
                'order.id' => ['order.id', 'order'],
                'source_data.pan' => ['source_data.pan', 'source_data_pan'],
                'source_data.sub_type' => ['source_data.sub_type', 'source_data_sub_type'],
                'source_data.type' => ['source_data.type', 'source_data_type'],
                default => [$key]
            };
            foreach ($fallbacks as $path) {
                $value = data_get($object, $path);
                if ($value !== null) {
                    return $this->string($value);
                }
            }

            return '';
        }, self::TRANSACTION_KEYS);
    }

    private function verify(array $values, ?string $signature): bool
    {
        if (! $signature) {
            return false;
        }
        $secret = config('paymob.keys.hmac');
        if (! $secret) {
            throw new ConfigurationException('PAYMOB_HMAC_SECRET is not configured.');
        }

        return hash_equals(strtolower($signature), hash_hmac('sha512', implode('', $values), $secret));
    }

    private function object(array $payload): array
    {
        return isset($payload['obj']) && is_array($payload['obj']) ? $payload['obj'] : $payload;
    }

    private function string(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
