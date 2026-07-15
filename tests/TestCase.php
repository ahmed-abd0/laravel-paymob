<?php

namespace Paymob\Laravel\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Paymob\Laravel\Concerns\Billable;
use Paymob\Laravel\PaymobServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [PaymobServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    protected function setUpConfig(): void
    {
        config()->set('paymob.keys.secret', 'sk_test_fake_secret_key');
        config()->set('paymob.keys.public', 'pk_test_fake_public_key');
        config()->set('paymob.keys.api', 'fake_api_key');
        config()->set('paymob.keys.hmac', 'fake_hmac_secret');
        config()->set('paymob.integrations.card_3ds', 465537);
        config()->set('paymob.integrations.default', 465537);
        config()->set('paymob.integrations.moto', 465538);
        config()->set('paymob.webhooks.subscription_secret', 'test_subscription_secret');
    }

    protected function fakeSuccessResponse(array $data = [], int $status = 200): void
    {
        Http::fake(fn () => Http::response($data, $status));
    }

    protected function fakeApiError(int $status = 422, string $message = 'Validation error'): void
    {
        Http::fake(fn () => Http::response(['message' => $message], $status));
    }

    protected function createBillable(array $attributes = []): Model
    {
        return new class($attributes) extends Model
        {
            protected $table = 'users';
            protected $guarded = [];
            public $timestamps = false;
            use Billable;
            public function __construct(array $attributes = [])
            {
                parent::__construct($attributes);
            }
        };
    }

    protected function ensureUserTable(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name')->default('Customer');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->timestamps();
            });
        }
    }
}
