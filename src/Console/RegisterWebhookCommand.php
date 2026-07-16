<?php

namespace Paymob\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Builder;
use Paymob\Laravel\Billing\SubscriptionManager;
use Paymob\Laravel\Models\Subscription;
use Paymob\Laravel\Support\SubscriptionWebhookUrl;

final class RegisterWebhookCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'paymob:webhook
        {subscription? : Local ID, Paymob ID, local:<id>, or remote:<id>; omit to update all remote subscriptions}
        {--url= : Absolute callback URL; defaults to PAYMOB_SUBSCRIPTION_WEBHOOK_URL or the package route}
        {--dry-run : Show the target subscriptions without calling Paymob}
        {--chunk=100 : Number of subscriptions processed per database chunk}
        {--force : Run in production without confirmation}';
    protected $description = 'Register or update the Paymob callback URL for one or all subscriptions.';

    public function handle(SubscriptionManager $subscriptions, SubscriptionWebhookUrl $urls): int
    {
        $chunk = (int) $this->option('chunk');
        if ($chunk < 1) {
            $this->components->error('The --chunk value must be at least 1.');

            return self::INVALID;
        }
        try {
            $url = $urls->resolve($this->option('url') ?: null);
        } catch (\Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
        $this->components->info('Subscription webhook: '.$urls->display($url));
        if ($identifier = $this->argument('subscription')) {
            return $this->registerOne($subscriptions, (string) $identifier, $url);
        }

        return $this->registerAll($subscriptions, $url, $chunk);
    }

    private function registerOne(SubscriptionManager $manager, string $identifier, string $url): int
    {
        $subscription = $this->findSubscription($identifier);
        if (! $subscription) {
            $this->components->error("No local Paymob subscription matched [{$identifier}].");

            return self::FAILURE;
        }
        if ($this->option('dry-run')) {
            $this->components->info('Would register '.$this->label($subscription).'.');

            return self::SUCCESS;
        }
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        return $this->register($manager, $subscription, $url) ? self::SUCCESS : self::FAILURE;
    }

    private function registerAll(SubscriptionManager $manager, string $url, int $chunk): int
    {
        $query = $this->remoteSubscriptions();
        $total = (clone $query)->count();
        if (! $total) {
            $this->components->warn('No local subscriptions with a Paymob ID were found.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->components->info("Would register {$total} subscription webhook(s).");

            return self::SUCCESS;
        }
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }
        $registered = $failed = 0;
        $query->chunkById($chunk, function ($items) use ($manager, $url, &$registered, &$failed) {
            foreach ($items as $subscription) {
                if ($this->register($manager, $subscription, $url)) {
                    $registered++;
                } else {
                    $failed++;
                }
            }
        });
        $this->components->info("Registered {$registered} subscription webhook(s); {$failed} failed.");

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function register(SubscriptionManager $manager, Subscription $subscription, string $url): bool
    {
        try {
            $manager->registerWebhook($subscription, $url);
            $this->line('Registered '.$this->label($subscription).'.');

            return true;
        } catch (\Throwable $e) {
            $this->components->error($this->label($subscription).': '.$e->getMessage());

            return false;
        }
    }

    private function findSubscription(string $identifier): ?Subscription
    {
        $model = config('paymob.models.subscription');
        if (str_starts_with($identifier, 'local:')) {
            return $model::query()->find(substr($identifier, 6));
        }
        if (str_starts_with($identifier, 'remote:')) {
            return $model::query()->where('paymob_id', substr($identifier, 7))->first();
        }
        $subscription = ctype_digit($identifier) ? $model::query()->find($identifier) : null;

        return $subscription ?? $model::query()->where('paymob_id', $identifier)->first();
    }

    private function remoteSubscriptions(): Builder
    {
        $model = config('paymob.models.subscription');

        return $model::query()->whereNotNull('paymob_id')->orderBy((new $model)->getKeyName());
    }

    private function label(Subscription $subscription): string
    {
        return "subscription #{$subscription->getKey()} (Paymob {$subscription->paymob_id})";
    }
}
