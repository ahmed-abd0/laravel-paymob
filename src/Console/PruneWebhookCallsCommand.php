<?php

namespace Paymob\Laravel\Console;

use Illuminate\Console\Command;

final class PruneWebhookCallsCommand extends Command
{
    protected $signature = 'paymob:prune-webhooks {--days=}';
    protected $description = 'Delete old processed Paymob webhook calls.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('paymob.webhooks.retain_days', 90));

        $deleted = config('paymob.models.webhook_call')::query()->where('status', 'processed')->where('created_at', '<', now()->subDays($days))->delete();

        $this->components->info("Deleted {$deleted} processed webhook calls.");

        return self::SUCCESS;
    }
}
