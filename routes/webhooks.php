<?php

use Illuminate\Support\Facades\Route;
use Paymob\Laravel\Http\Controllers\WebhookController;

if (config('paymob.webhooks.enabled')) {

    Route::prefix(config('paymob.webhooks.prefix'))
        ->middleware(config('paymob.webhooks.middleware', ['api']))
        ->group(function () {
            Route::post('/', [WebhookController::class, 'handle'])
                ->name('paymob.webhooks.handle');

            Route::post('/transaction', [WebhookController::class, 'transaction'])
                ->name('paymob.webhooks.transaction');

            Route::post('/token', [WebhookController::class, 'token'])
                ->name('paymob.webhooks.token');

            Route::post('/subscription', [WebhookController::class, 'subscription'])
                ->name('paymob.webhooks.subscription');
        });
}
