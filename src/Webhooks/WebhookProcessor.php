<?php

namespace Paymob\Laravel\Webhooks;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Paymob\Laravel\Enums\SubscriptionStatus;
use Paymob\Laravel\Enums\TransactionStatus;
use Paymob\Laravel\Enums\WebhookType;
use Paymob\Laravel\Events\PaymentMethodUpdated;
use Paymob\Laravel\Events\SubscriptionUpdated;
use Paymob\Laravel\Events\TransactionUpdated;
use Paymob\Laravel\Events\WebhookHandled;
use Paymob\Laravel\Models\PaymentMethod;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Models\Transaction;
use Paymob\Laravel\Models\WebhookCall;

final class WebhookProcessor
{
    public function process(WebhookCall $call): void
    {
        DB::transaction(function () use ($call) {
            /** @var WebhookCall $locked */
            $locked = config('paymob.models.webhook_call')::query()->lockForUpdate()->findOrFail($call->getKey());
            if ($locked->status === 'processed') return;
            $locked->increment('attempts');
            try {
                $event = match (WebhookType::from($locked->type)) {
                    WebhookType::TRANSACTION => $this->transaction($locked->payload),
                    WebhookType::TOKEN => $this->token($locked->payload),
                    WebhookType::SUBSCRIPTION => $this->subscription($locked->payload),
                    default => null
                };
                $locked->update(['status' => 'processed', 'error' => null, 'processed_at' => now()]);
                $this->afterCommit(fn() => $event && event($event));
                $this->afterCommit(fn() => event(new WebhookHandled($locked->fresh())));
            } catch (\Throwable $e) {
                $locked->update(['status' => 'failed', 'error' => $e->getMessage()]);
                throw $e;
            }
        });
    }

    private function transaction(array $payload): TransactionUpdated
    {
        $data = $this->object($payload);
        $subscription = $this->findSubscription($data);
        $id = (string) ($data['id'] ?? hash('sha256', json_encode($data)));
        /** @var Transaction $transaction */
        $transaction = config('paymob.models.transaction')::query()->firstOrNew(['paymob_id' => $id]);
        $transaction->fill([
            'subscription_id' => $subscription?->getKey(),
            'order_id' => $this->scalar(data_get($data, 'order.id') ?? $data['order'] ?? null),
            'merchant_order_id' => $this->merchantOrderId($data),
            'intention_id' => $data['intention_id'] ?? data_get($data, 'payment_key_claims.intention_id'),
            'parent_transaction_id' => $this->scalar($data['parent_transaction'] ?? null),
            'status' => TransactionStatus::fromPayload($data),
            'amount_cents' => (int) ($data['amount_cents'] ?? 0),
            'refunded_amount_cents' => (int) ($data['refunded_amount_cents'] ?? 0),
            'captured_amount_cents' => (int) ($data['captured_amount'] ?? $data['captured_amount_cents'] ?? 0),
            'currency' => $data['currency'] ?? config('paymob.currency', 'EGP'),
            'integration_id' => $data['integration_id'] ?? null,
            'source_type' => data_get($data, 'source_data.type') ?? $data['source_data_type'] ?? null,
            'source_subtype' => data_get($data, 'source_data.sub_type') ?? $data['source_data_sub_type'] ?? null,
            'source_pan' => data_get($data, 'source_data.pan') ?? $data['source_data_pan'] ?? null,
            'success' => $this->bool($data['success'] ?? false),
            'pending' => $this->bool($data['pending'] ?? false),
            'is_refund' => $this->bool($data['is_refund'] ?? false),
            'is_void' => $this->bool($data['is_void'] ?? false),
            'is_capture' => $this->bool($data['is_capture'] ?? false),
            'occurred_at' => $this->dateTime($data['created_at'] ?? null),
            'payload' => $this->withoutSecrets($data)
        ]);
        if ($subscription) $transaction->billable()->associate($subscription->billable);
        $transaction->save();

        if ($subscription) {
            $status = TransactionStatus::fromPayload($data);
            $remoteId = $this->scalar($data['subscription_id'] ?? data_get($data, 'subscription.id') ?? data_get($data, 'order.subscription_id'));
            $subscription->fill(array_filter([
                'paymob_id' => $remoteId ?: $subscription->paymob_id,
                'status' => match ($status) {
                    TransactionStatus::SUCCEEDED, TransactionStatus::CAPTURED => SubscriptionStatus::ACTIVE,
                    TransactionStatus::FAILED => $subscription->incomplete() ? SubscriptionStatus::INCOMPLETE : SubscriptionStatus::PAST_DUE,
                    default => $subscription->status
                },
                'activated_at' => in_array($status, [TransactionStatus::SUCCEEDED, TransactionStatus::CAPTURED], true) ? ($subscription->activated_at ?? now()) : $subscription->activated_at
            ], fn($value) => $value !== null))->save();
            $this->afterCommit(fn() => event(new SubscriptionUpdated($subscription->fresh())));
        }
        return new TransactionUpdated($transaction->fresh());
    }

    private function token(array $payload): PaymentMethodUpdated
    {
        $data = $this->object($payload);
        $token = (string) ($data['token'] ?? '');
        $orderId = $this->scalar($data['order_id'] ?? null);
        $transaction = $orderId ? config('paymob.models.transaction')::query()->where('order_id', $orderId)->latest('id')->first() : null;
        $subscription = $transaction?->subscription;
        $hash = hash('sha256', $token);
        /** @var PaymentMethod $method */
        $method = config('paymob.models.payment_method')::query()->firstOrNew(['token_hash' => $hash]);
        $method->fill([
            'subscription_id' => $subscription?->getKey(),
            'paymob_id' => isset($data['id']) ? (string) $data['id'] : $method->paymob_id,
            'token' => $token,
            'masked_pan' => $data['masked_pan'] ?? null,
            'brand' => $data['card_subtype'] ?? null,
            'primary' => $subscription ? !$subscription->paymentMethods()->where('primary', true)->exists() : false,
            'payload' => $this->withoutSecrets($data)
        ]);
        if ($subscription) $method->billable()->associate($subscription->billable);
        $method->save();
        return new PaymentMethodUpdated($method->fresh());
    }

    private function subscription(array $payload): SubscriptionUpdated
    {
        $data = $this->object($payload);
        $id = $this->scalar($data['id'] ?? $data['subscription_id'] ?? null);
        $reference = $data['merchant_order_id'] ?? $data['reference'] ?? data_get($data, 'metadata.subscription_reference');
        /** @var Subscription|null $subscription */
        $subscription = config('paymob.models.subscription')::query()
            ->when($id, fn($query) => $query->where('paymob_id', $id))
            ->when(!$id && $reference, fn($query) => $query->where('reference', $reference))
            ->first();
        if (!$subscription && ($initialTransaction = $this->scalar($data['initial_transaction'] ?? null))) {
            $subscription = config('paymob.models.transaction')::query()->where('paymob_id', $initialTransaction)->first()?->subscription;
        }
        if (!$subscription) throw new \RuntimeException('Unable to match the Paymob subscription webhook to a local subscription.');
        $state = SubscriptionStatus::fromPaymob($data['state'] ?? $data['status'] ?? null);
        $subscription->fill([
            'paymob_id' => $id ?: $subscription->paymob_id,
            'remote_plan_id' => isset($data['plan_id']) ? (string) $data['plan_id'] : $subscription->remote_plan_id,
            'status' => $state,
            'amount_cents' => (int) ($data['amount_cents'] ?? $subscription->amount_cents),
            'currency' => $data['currency'] ?? $subscription->currency,
            'starts_at' => $this->date($data['starts_at'] ?? null) ?? $subscription->starts_at,
            'ends_at' => $this->date($data['ends_at'] ?? null) ?? $subscription->ends_at,
            'next_billing_at' => $this->date($data['next_billing'] ?? $data['next_billing_at'] ?? null) ?? $subscription->next_billing_at,
            'suspended_at' => $state === SubscriptionStatus::SUSPENDED ? ($subscription->suspended_at ?? now()) : null,
            'canceled_at' => $state === SubscriptionStatus::CANCELED ? ($subscription->canceled_at ?? now()) : $subscription->canceled_at,
            'activated_at' => $state === SubscriptionStatus::ACTIVE ? ($subscription->activated_at ?? now()) : $subscription->activated_at,
            'synced_at' => now(),
            'payload' => $this->withoutSecrets($data)
        ])->save();
        return new SubscriptionUpdated($subscription->fresh());
    }

    private function findSubscription(array $data): ?Subscription
    {
        $reference = $this->merchantOrderId($data)
            ?? data_get($data, 'payment_key_claims.extra.subscription_reference')
            ?? data_get($data, 'payment_key_claims.extras.subscription_reference')
            ?? data_get($data, 'extras.subscription_reference');
        if ($reference) {
            $subscription = config('paymob.models.subscription')::query()->where('reference', $reference)->first();
            if ($subscription) return $subscription;
        }
        $intentionId = $data['intention_id'] ?? data_get($data, 'payment_key_claims.intention_id');
        if ($intentionId) return config('paymob.models.subscription')::query()->where('intention_id', $intentionId)->first();
        $remoteId = $data['subscription_id'] ?? data_get($data, 'subscription.id');
        return $remoteId ? config('paymob.models.subscription')::query()->where('paymob_id', $remoteId)->first() : null;
    }

    private function merchantOrderId(array $data): ?string
    {
        return $this->scalar(data_get($data, 'order.merchant_order_id') ?? $data['merchant_order_id'] ?? null);
    }

    private function object(array $payload): array
    {
        return isset($payload['obj']) && is_array($payload['obj']) ? $payload['obj'] : $payload;
    }
    private function scalar(mixed $value): ?string
    {
        return is_scalar($value) && $value !== '' ? (string) $value : null;
    }
    private function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
    private function date(mixed $value): ?string
    {
        return $value ? Carbon::parse($value)->toDateString() : null;
    }
    private function dateTime(mixed $value): mixed
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function withoutSecrets(array $data): array
    {
        unset($data['token'], $data['client_secret'], $data['payment_token']);
        if (isset($data['payment_key_claims']['billing_data'])) $data['payment_key_claims']['billing_data'] = '[redacted]';
        return $data;
    }

    private function afterCommit(callable $callback): void
    {
        $safe = function () use ($callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                report($e);
            }
        };
        if (config('paymob.webhooks.dispatch_after_commit', true)) DB::afterCommit($safe);
        else $safe();
    }
}
