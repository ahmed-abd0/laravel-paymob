<?php

namespace Paymob\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Paymob\Laravel\PaymobServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array { return [PaymobServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
