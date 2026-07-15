<?php

namespace Paymob\Laravel\Resources;

use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Support\PaymobResponse;

final class SavedCards
{
    public function __construct(private readonly PaymobHttpClient $http, private readonly Intentions $intentions) {}

    public function createTokenIntention(IntentionData|array $data): PaymobResponse { return $this->intentions->create($data); }

    public function customerInitiated(IntentionData|array $data, array $cardTokens): PaymobResponse
    {
        $payload = $data instanceof IntentionData ? $data->cardTokens($cardTokens)->toArray() : array_merge($data, ['card_tokens' => array_values($cardTokens)]);
        return $this->intentions->create($payload);
    }

    public function merchantInitiatedIntention(IntentionData|array $data): PaymobResponse { return $this->intentions->create($data); }

    public function payMoto(string $cardToken, string $paymentToken): PaymobResponse
    {
        $url = rtrim(config('paymob.moto_base_url'), '/').'/api/acceptance/payments/pay';
        return $this->http->public('POST', $url, ['source' => ['identifier' => $cardToken, 'subtype' => 'TOKEN'], 'payment_token' => $paymentToken]);
    }
}
