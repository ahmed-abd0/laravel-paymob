<?php

namespace Paymob\Laravel\Data;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class IntentionData implements Arrayable
{
    private array $items = [];
    private array $extras = [];
    private array $cardTokens = [];

    public function __construct(
        public int $amount,
        public BillingData $billingData,
        public array $paymentMethods,
        public string $currency = 'EGP',
        public ?string $specialReference = null,
        public ?string $notificationUrl = null,
        public ?string $redirectionUrl = null,
        public ?int $expiration = null,
        public ?int $subscriptionPlanId = null,
        public ?int $subscriptionId = null,
        public ?string $subscriptionStartDate = null
    ) {
        $this->specialReference ??= (string) Str::uuid();
    }

    public function items(Item|array ...$items): self
    {
        $this->items = array_map(fn(Item|array $item) => $item instanceof Item ? $item->toArray() : $item, $items);
        return $this;
    }

    public function extras(array $extras): self { $this->extras = $extras; return $this; }
    public function cardTokens(array $tokens): self { $this->cardTokens = array_values($tokens); return $this; }

    public function toArray(): array
    {
        if ($this->amount <= 0) throw new InvalidArgumentException('The intention amount must be greater than zero.');
        if (!$this->paymentMethods || collect($this->paymentMethods)->contains(fn($id) => !is_numeric($id) || (int) $id <= 0)) throw new InvalidArgumentException('Valid Paymob integration IDs are required.');
        if ($this->items && array_sum(array_column($this->items, 'amount')) !== $this->amount) throw new InvalidArgumentException('The intention amount must equal the sum of item amounts.');
        return array_filter([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_methods' => array_values($this->paymentMethods),
            'items' => $this->items ?: null,
            'billing_data' => $this->billingData->toArray(),
            'extras' => $this->extras ?: null,
            'card_tokens' => $this->cardTokens ?: null,
            'special_reference' => $this->specialReference,
            'notification_url' => $this->notificationUrl,
            'redirection_url' => $this->redirectionUrl,
            'expiration' => $this->expiration,
            'subscription_plan_id' => $this->subscriptionPlanId,
            'subscriptionv2_id' => $this->subscriptionId,
            'subscription_start_date' => $this->subscriptionStartDate
        ], fn($value) => $value !== null);
    }
}
