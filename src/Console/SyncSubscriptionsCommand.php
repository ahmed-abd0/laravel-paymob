<?php

namespace Paymob\Laravel\Console;

use Illuminate\Console\Command;
use Paymob\Laravel\Billing\PlanManager;
use Paymob\Laravel\Billing\SubscriptionManager;

final class SyncSubscriptionsCommand extends Command
{
    protected $signature = 'paymob:sync-subscriptions {--plans : Sync remote plans before subscriptions} {--with-relations : Also sync transaction history and cards}';
    protected $description = 'Synchronize local Paymob plans and subscriptions with Paymob.';

    public function handle(PlanManager $plans, SubscriptionManager $subscriptions): int
    {
        $this->components->info("Expired {$subscriptions->expireIncomplete()} stale incomplete subscriptions.");

        if ($this->option('plans')) $this->components->info("Synced {$plans->syncAll()} Paymob plans.");

        $this->components->info("Synced {$subscriptions->syncAll((bool)$this->option('with-relations'))} Paymob subscriptions.");

        return self::SUCCESS;
    }
}
