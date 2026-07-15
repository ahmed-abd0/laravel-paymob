<?php

namespace Paymob\Laravel\Billing;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Enums\TransactionStatus;
use Paymob\Laravel\Exceptions\PaymobException;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Resources\Subscriptions;

final class SubscriptionManager
{
    public function __construct(private readonly Subscriptions $api) {}

    public function suspend(Subscription $subscription): Subscription
    {
        $this->guardRemoteId($subscription);
        $response = $this->api->suspend($subscription->paymob_id)->toArray();

        return $this->persist($subscription, $response + ['state' => 'suspended']);
    }

    public function resume(Subscription $subscription, ?string $nextBilling = null): Subscription
    {
        $this->guardRemoteId($subscription);
        if ($nextBilling) {
            $this->api->update($subscription->paymob_id, ['next_billing' => $nextBilling]);
        }
        $response = $this->api->resume($subscription->paymob_id)->toArray();

        return $this->persist($subscription, $response + ['state' => 'active']);
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $this->guardRemoteId($subscription);
        $response = $this->api->cancel($subscription->paymob_id)->toArray();

        return $this->persist($subscription, $response + ['state' => 'cancelled']);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        $this->guardRemoteId($subscription);
        $allowed = array_intersect_key($data, array_flip(['amount_cents', 'ends_at', 'next_billing']));
        $response = $this->api->update($subscription->paymob_id, $allowed)->toArray();

        return $this->persist($subscription, $response + $allowed);
    }

    public function sync(Subscription $subscription, bool $withRelations = false): Subscription
    {
        $this->guardRemoteId($subscription);
        $subscription = $this->persist($subscription, $this->api->find($subscription->paymob_id)->toArray());
        if ($withRelations) {
            $this->syncTransactions($subscription);
            $this->syncPaymentMethods($subscription);
        }

        return $subscription;
    }

    public function syncTransactions(Subscription $subscription): int
    {
        $this->guardRemoteId($subscription);
        $data = $this->api->transactions($subscription->paymob_id)->toArray();
        $rows = Arr::isList($data) ? $data : ($data['results'] ?? $data['data'] ?? []);
        foreach ($rows as $row) {
            $transaction = config('paymob.models.transaction')::query()->firstOrNew(['paymob_id' => (string) $row['id']]);
            $transaction->fill([
                'subscription_id' => $subscription->getKey(),
                'order_id' => $this->scalar(data_get($row, 'order.id') ?? $row['order'] ?? null),
                'merchant_order_id' => $this->scalar(data_get($row, 'order.merchant_order_id') ?? $row['merchant_order_id'] ?? null),
                'parent_transaction_id' => $this->scalar($row['parent_transaction'] ?? null),
                'status' => TransactionStatus::fromPayload($row),
                'amount_cents' => (int) ($row['amount_cents'] ?? 0),
                'refunded_amount_cents' => (int) ($row['refunded_amount_cents'] ?? 0),
                'captured_amount_cents' => (int) ($row['captured_amount'] ?? $row['captured_amount_cents'] ?? 0),
                'currency' => $row['currency'] ?? $subscription->currency,
                'integration_id' => $row['integration_id'] ?? null,
                'source_type' => data_get($row, 'source_data.type'),
                'source_subtype' => data_get($row, 'source_data.sub_type'),
                'source_pan' => data_get($row, 'source_data.pan'),
                'success' => $this->bool($row['success'] ?? false),
                'pending' => $this->bool($row['pending'] ?? false),
                'is_refund' => $this->bool($row['is_refund'] ?? false),
                'is_void' => $this->bool($row['is_void'] ?? false),
                'is_capture' => $this->bool($row['is_capture'] ?? false),
                'occurred_at' => $row['created_at'] ?? null,
                'payload' => $row,
            ]);
            $transaction->billable()->associate($subscription->billable);
            $transaction->save();
        }

        return count($rows);
    }

    public function syncPaymentMethods(Subscription $subscription): int
    {
        $this->guardRemoteId($subscription);
        $data = $this->api->cards($subscription->paymob_id)->toArray();
        $rows = Arr::isList($data) ? $data : ($data['results'] ?? $data['data'] ?? []);
        if (collect($rows)->contains(fn (array $row) => (bool) ($row['is_primary'] ?? false))) {
            $subscription->paymentMethods()->update(['primary' => false]);
        }
        foreach ($rows as $row) {
            $token = (string) ($row['token'] ?? '');
            $query = config('paymob.models.payment_method')::query();
            $method = isset($row['id']) ? $query->firstOrNew(['paymob_id' => (string) $row['id']]) : $query->firstOrNew(['token_hash' => hash('sha256', $token)]);
            $method->fill([
                'subscription_id' => $subscription->getKey(),
                'paymob_id' => isset($row['id']) ? (string) $row['id'] : $method->paymob_id,
                'token' => $token ?: $method->token,
                'token_hash' => $token ? hash('sha256', $token) : $method->token_hash,
                'masked_pan' => $row['masked_pan'] ?? $method->masked_pan,
                'brand' => data_get($row, 'card_data.sub_type') ?? data_get($row, 'card_data.card_subtype') ?? $method->brand,
                'primary' => (bool) ($row['is_primary'] ?? false),
                'payload' => Arr::except($row, ['token']),
            ]);
            $method->billable()->associate($subscription->billable);
            $method->save();
        }

        return count($rows);
    }

    public function expireIncomplete(): int
    {
        return config('paymob.models.subscription')::query()
            ->where('status', SubscriptionStatus::INCOMPLETE->value)
            ->where('created_at', '<=', now()->subSeconds(config('paymob.checkout.expiration', 3600)))
            ->update(['status' => SubscriptionStatus::INCOMPLETE_EXPIRED->value, 'updated_at' => now()]);
    }

    public function syncAll(bool $withRelations = false): int
    {
        $count = 0;
        config('paymob.models.subscription')::query()->whereNotNull('paymob_id')->chunkById(100, function ($subscriptions) use (&$count, $withRelations) {
            foreach ($subscriptions as $subscription) {
                try {
                    $this->sync($subscription, $withRelations);
                    $count++;
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });

        return $count;
    }

    public function persist(Subscription $subscription, array $data): Subscription
    {
        return DB::transaction(function () use ($subscription, $data) {
            $currentStatus = $subscription->status instanceof SubscriptionStatus ? $subscription->status->value : $subscription->status;
            $state = SubscriptionStatus::fromPaymob($data['state'] ?? $data['status'] ?? $currentStatus);
            $subscription->fill([
                'paymob_id' => isset($data['id']) ? (string) $data['id'] : $subscription->paymob_id,
                'remote_plan_id' => isset($data['plan_id']) ? (string) $data['plan_id'] : $subscription->remote_plan_id,
                'status' => $state,
                'amount_cents' => (int) ($data['amount_cents'] ?? $subscription->amount_cents),
                'currency' => $data['currency'] ?? $subscription->currency,
                'starts_at' => $this->date($data['starts_at'] ?? $subscription->starts_at),
                'ends_at' => $this->date($data['ends_at'] ?? $subscription->ends_at),
                'next_billing_at' => $this->date($data['next_billing'] ?? $data['next_billing_at'] ?? $subscription->next_billing_at),
                'suspended_at' => $state === SubscriptionStatus::SUSPENDED ? ($subscription->suspended_at ?? now()) : null,
                'canceled_at' => $state === SubscriptionStatus::CANCELED ? ($subscription->canceled_at ?? now()) : $subscription->canceled_at,
                'activated_at' => $state === SubscriptionStatus::ACTIVE ? ($subscription->activated_at ?? now()) : $subscription->activated_at,
                'synced_at' => now(),
                'payload' => $data,
            ])->save();

            return $subscription;
        });
    }

    private function guardRemoteId(Subscription $subscription): void
    {
        if (! $subscription->paymob_id) {
            throw new PaymobException('The subscription is not active at Paymob yet.');
        }
    }

    private function date(mixed $value): mixed
    {
        if (! $value) {
            return null;
        }

        return $value instanceof \DateTimeInterface ? $value : Carbon::parse($value)->toDateString();
    }

    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
