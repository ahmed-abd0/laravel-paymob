<?php

namespace Paymob\Laravel\Resources;

use InvalidArgumentException;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class Transactions
{
    public function __construct(private readonly PaymobHttpClient $http) {}

    public function byOrder(int|string|null $orderId = null, ?string $merchantOrderId = null): PaymobResponse
    {
        if ($orderId === null && $merchantOrderId === null) throw new InvalidArgumentException('An order ID or merchant order ID is required.');
        return $this->http->bearer('POST', '/api/ecommerce/orders/transaction_inquiry', array_filter([
            'order_id' => $orderId,
            'merchant_order_id' => $merchantOrderId
        ], fn($value) => $value !== null));
    }

    public function find(int|string $transactionId): PaymobResponse
    {
        return $this->http->bearer('GET', "/api/acceptance/transactions/{$transactionId}");
    }
}
