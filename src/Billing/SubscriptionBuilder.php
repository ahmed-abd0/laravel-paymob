<?php

namespace Paymob\Laravel\Billing;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Paymob\Laravel\Data\BillingData;
use Paymob\Laravel\Data\IntentionData;
use Paymob\Laravel\Data\Item;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Models\Plan;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Resources\Intentions;
use Paymob\Laravel\Support\Checkout;

final class SubscriptionBuilder
{
    private Model $billable;
    private string $name;
    private Plan|int|string $plan;
    private ?BillingData $billingData = null;
    private ?int $amountCents = null;
    private ?string $currency = null;
    private array $paymentMethods = [];
    private array $metadata = [];
    private ?DateTimeInterface $trialEndsAt = null;
    private ?string $startDate = null;
    private ?string $notificationUrl = null;
    private ?string $redirectionUrl = null;
    private ?int $expiration = null;
    private ?string $description = null;

    public function __construct(private readonly Intentions $intentions) {}

    public function for(Model $billable, string $name, Plan|int|string $plan): self
    {
        $clone = clone $this;
        $clone->billable = $billable;
        $clone->name = $name;
        $clone->plan = $plan;
        return $clone;
    }

    public function billing(BillingData|array $data): self
    {
        $this->billingData = $data instanceof BillingData ? $data : BillingData::fromArray($data);
        return $this;
    }
    public function amount(int $amountCents): self
    {
        $this->amountCents = $amountCents;
        return $this;
    }
    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);
        return $this;
    }
    public function paymentMethods(array $integrationIds): self
    {
        $this->paymentMethods = array_map('intval', $integrationIds);
        return $this;
    }
    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }
    public function trialUntil(DateTimeInterface $date): self
    {
        $this->trialEndsAt = $date;
        $this->startDate = $date->format('Y-m-d');
        return $this;
    }
    public function trialDays(int $days): self
    {
        return $this->trialUntil(now()->addDays($days));
    }
    public function startAt(DateTimeInterface|string $date): self
    {
        $this->startDate = $date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date;
        return $this;
    }
    public function callbackUrls(?string $notificationUrl, ?string $redirectionUrl): self
    {
        $this->notificationUrl = $notificationUrl;
        $this->redirectionUrl = $redirectionUrl;
        return $this;
    }
    public function expiresIn(int $seconds): self
    {
        $this->expiration = $seconds;
        return $this;
    }
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function checkout(): Checkout
    {
        $key = 'paymob:subscription:create:' . hash('sha256', $this->billable->getMorphClass() . '|' . $this->billable->getKey() . '|' . $this->name);
        return Cache::lock($key, 60)->block(10, fn() => $this->createCheckout());
    }

    private function createCheckout(): Checkout
    {
        $existing = $this->billable->paymobSubscriptions()->where('name', $this->name)->latest('id')->first();
        if ($existing?->status === SubscriptionStatus::INCOMPLETE && $existing->created_at?->lte(now()->subSeconds(config('paymob.checkout.expiration', 3600)))) {
            $existing->update(['status' => SubscriptionStatus::INCOMPLETE_EXPIRED]);
        }
        if ($existing && !in_array($existing->status, [SubscriptionStatus::CANCELED, SubscriptionStatus::EXPIRED, SubscriptionStatus::INCOMPLETE_EXPIRED], true)) {
            throw new InvalidArgumentException("A non-terminal subscription named [{$this->name}] already exists.");
        }
        $plan = $this->plan instanceof Plan ? $this->plan : null;
        $remotePlanId = (string) ($plan?->paymob_id ?? $this->plan);
        $amount = $this->amountCents ?? $plan?->amount_cents;
        if (!$amount) throw new InvalidArgumentException('A positive initial transaction amount is required.');
        $billing = $this->billingData ?? BillingData::fromArray(method_exists($this->billable, 'paymobBillingData') ? $this->billable->paymobBillingData() : []);
        $payments = $this->paymentMethods ?: array_values(array_filter([(int) config('paymob.integrations.card_3ds'), (int) config('paymob.integrations.default')]));
        if (!$payments) throw new InvalidArgumentException('Configure PAYMOB_CARD_3DS_INTEGRATION_ID or pass paymentMethods().');
        $subscriptionClass = config('paymob.models.subscription');
        /** @var Subscription $subscription */
        $subscription = new $subscriptionClass;
        $reference = (string) Str::uuid();
        $subscription->fill([
            'paymob_plan_id' => $plan?->getKey(),
            'name' => $this->name,
            'remote_plan_id' => $remotePlanId,
            'reference' => $reference,
            'status' => SubscriptionStatus::INCOMPLETE,
            'amount_cents' => $plan?->amount_cents ?? $amount,
            'currency' => $this->currency ?? $plan?->currency ?? config('paymob.currency', 'EGP'),
            'starts_at' => $this->startDate,
            'trial_ends_at' => $this->trialEndsAt,
            'metadata' => $this->metadata
        ]);
        $subscription->billable()->associate($this->billable);
        $subscription->save();

        $extras = array_merge($this->metadata, [
            'subscription_reference' => $reference,
            'billable_type' => $this->billable->getMorphClass(),
            'billable_id' => (string) $this->billable->getKey()
        ]);
        $data = new IntentionData(
            amount: $amount,
            billingData: $billing,
            paymentMethods: $payments,
            currency: $subscription->currency,
            specialReference: $reference,
            notificationUrl: $this->notificationUrl ?? config('paymob.checkout.notification_url') ?? $this->webhookUrl(),
            redirectionUrl: $this->redirectionUrl ?? config('paymob.checkout.redirection_url'),
            expiration: $this->expiration ?? config('paymob.checkout.expiration', 3600),
            subscriptionPlanId: (int) $remotePlanId,
            subscriptionStartDate: $this->startDate
        );
        $data->items(new Item($plan?->name ?? $this->name, $amount, 1, $this->description ?? 'Subscription enrollment'))->extras($extras);

        try {
            $response = $this->intentions->create($data);
            $payload = $response->toArray();
            $subscription->update([
                'intention_id' => $payload['id'] ?? null,
                'intention_order_id' => $payload['intention_order_id'] ?? data_get($payload, 'payment_keys.0.order_id'),
                'client_secret' => $payload['client_secret'] ?? null,
                'payload' => $payload
            ]);
        } catch (\Throwable $e) {
            $subscription->update(['metadata' => array_merge($subscription->metadata ?? [], ['creation_error' => $e->getMessage()])]);
            throw $e;
        }

        $clientSecret = (string) $subscription->client_secret;
        return new Checkout($this->intentions->checkoutUrl($clientSecret), $clientSecret, $response, $subscription->refresh());
    }

    private function webhookUrl(): ?string
    {
        return app('router')->has('paymob.webhooks.handle') ? route('paymob.webhooks.handle') : null;
    }
}
