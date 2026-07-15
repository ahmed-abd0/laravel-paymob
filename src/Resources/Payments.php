<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class Payments
{
    public function __construct(private readonly PaymobHttpClient $http) {}

    public function refund(int|string $transactionId, int $amountCents): PaymobResponse
    {
        return $this->http->secret('POST', '/api/acceptance/void_refund/refund', ['transaction_id' => $transactionId, 'amount_cents' => $amountCents]);
    }

    public function void(int|string $transactionId): PaymobResponse
    {
        return $this->http->secret('POST', '/api/acceptance/void_refund/void', ['transaction_id' => $transactionId]);
    }

    public function capture(int|string $transactionId, int $amountCents): PaymobResponse
    {
        return $this->http->secret('POST', '/api/acceptance/capture', ['transaction_id' => $transactionId, 'amount_cents' => $amountCents]);
    }
}
