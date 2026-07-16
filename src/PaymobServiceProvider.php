<?php

namespace Paymob\Laravel;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;
use Paymob\Laravel\Console\PruneWebhookCallsCommand;
use Paymob\Laravel\Console\RegisterWebhookCommand;
use Paymob\Laravel\Console\SyncSubscriptionsCommand;
use Paymob\Laravel\Http\PaymobHttpClient;
use Paymob\Laravel\Http\TokenManager;

final class PaymobServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/paymob.php', 'paymob');
        $this->app->singleton(TokenManager::class, fn ($app) => new TokenManager($app->make(Factory::class), $app->make(Repository::class)));
        $this->app->singleton(PaymobHttpClient::class);
        $this->app->singleton(Paymob::class);
        $this->app->alias(Paymob::class, 'paymob');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/webhooks.php');
        $this->publishes([__DIR__.'/../config/paymob.php' => config_path('paymob.php')], 'paymob-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'paymob-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncSubscriptionsCommand::class,
                PruneWebhookCallsCommand::class,
                RegisterWebhookCommand::class,
            ]);
        }
    }
}
