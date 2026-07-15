<?php

namespace Paymob\Laravel\Support;

use Paymob\Laravel\Models\Subscription;

final class Checkout
{
    public function __construct(
        public readonly string $url,
        public readonly string $clientSecret,
        public readonly PaymobResponse $response,
        public readonly ?Subscription $subscription = null
    ) {}

    public function redirect(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->away($this->url);
    }
}
