<?php

namespace Paymob\Laravel\Tests\Fixtures;

final class Fixtures
{
    public static function intentionResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'intention-001',
            'client_secret' => 'test_client_secret_xyz',
            'amount' => 10000,
            'currency' => 'EGP',
            'payment_methods' => [465537],
            'intention_order_id' => 12345,
        ], $overrides);
    }

    public static function transactionPayload(array $overrides = []): array
    {
        return array_merge([
            'obj' => [
                'id' => 98765,
                'amount_cents' => 10000,
                'success' => true,
                'pending' => false,
                'is_refunded' => false,
                'is_voided' => false,
                'is_capture' => false,
                'is_3d_secure' => true,
                'is_auth' => false,
                'is_standalone_payment' => true,
                'error_occured' => false,
                'has_parent_transaction' => false,
                'currency' => 'EGP',
                'created_at' => '2026-01-15T10:00:00+0200',
                'integration_id' => 465537,
                'order' => ['id' => 12345, 'merchant_order_id' => 'ORDER-001'],
                'owner' => 'owner@test.com',
                'source_data' => [
                    'type' => 'CARD',
                    'sub_type' => 'VISA',
                    'pan' => '411111******1111',
                ],
            ],
        ], $overrides);
    }

    public static function tokenPayload(array $overrides = []): array
    {
        return array_merge([
            'obj' => [
                'id' => 55555,
                'token' => 'fake_tokenizeR_token_abc123',
                'masked_pan' => '411111******1111',
                'card_subtype' => 'VISA',
                'email' => 'test@example.com',
                'merchant_id' => 1001,
                'order_id' => 12345,
                'created_at' => '2026-01-15T10:00:00+0200',
            ],
            'type' => 'TOKEN',
        ], $overrides);
    }

    public static function subscriptionPayload(array $overrides = []): array
    {
        return array_merge([
            'obj' => [
                'id' => 77777,
                'state' => 'activated',
                'amount_cents' => 49900,
                'currency' => 'EGP',
                'plan_id' => 88888,
                'starts_at' => '2026-01-15',
                'ends_at' => '2027-01-15',
                'next_billing' => '2026-02-15',
                'initial_transaction' => 98765,
                'merchant_order_id' => null,
                'reference' => null,
            ],
        ], $overrides);
    }

    public static function planResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 88888,
            'name' => 'Test Monthly Plan',
            'frequency' => 30,
            'plan_type' => 'rent',
            'amount_cents' => 49900,
            'currency' => 'EGP',
            'integration' => 465538,
            'reminder_days' => 2,
            'retrial_days' => 2,
            'number_of_deductions' => null,
            'use_transaction_amount' => false,
            'is_active' => true,
            'webhook_url' => null,
        ], $overrides);
    }

    public static function subscriptionRemoteResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 77777,
            'state' => 'activated',
            'amount_cents' => 49900,
            'currency' => 'EGP',
            'plan_id' => 88888,
            'starts_at' => '2026-01-15',
            'ends_at' => '2027-01-15',
            'next_billing' => '2026-02-15',
        ], $overrides);
    }
}
