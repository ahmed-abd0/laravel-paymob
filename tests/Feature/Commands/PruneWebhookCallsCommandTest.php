<?php

use Illuminate\Support\Facades\Schema;
use Paymob\Laravel\Models\WebhookCall;

beforeEach(function () {
    $this->setUpConfig();
});

it('prunes old processed webhook calls', function () {
    $table = config('paymob.tables.webhook_calls');
    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($t) {
            $t->uuid('id')->primary();
            $t->string('type', 40)->index();
            $t->string('external_id')->nullable()->index();
            $t->string('event')->nullable();
            $t->string('signature', 160)->nullable();
            $t->boolean('valid_signature')->default(false);
            $t->string('payload_hash', 64)->unique();
            $t->longText('payload');
            $t->string('status', 30)->default('pending')->index();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    $old = WebhookCall::create([
        'type' => 'transaction',
        'payload_hash' => hash('sha256', 'old_processed'),
        'payload' => ['type' => 'TRANSACTION'],
        'status' => 'processed',
    ]);
    $old->update(['created_at' => now()->subDays(100)]);

    $this->artisan('paymob:prune-webhooks', ['--days' => 90])
        ->expectsOutputToContain('Deleted 1')
        ->assertExitCode(0);

    expect(WebhookCall::find($old->id))->toBeNull();
});

it('respects --days option', function () {
    $table = config('paymob.tables.webhook_calls');
    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($t) {
            $t->uuid('id')->primary();
            $t->string('type', 40)->index();
            $t->string('external_id')->nullable()->index();
            $t->string('event')->nullable();
            $t->string('signature', 160)->nullable();
            $t->boolean('valid_signature')->default(false);
            $t->string('payload_hash', 64)->unique();
            $t->longText('payload');
            $t->string('status', 30)->default('pending')->index();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    $old = WebhookCall::create([
        'type' => 'transaction',
        'payload_hash' => hash('sha256', 'old_days_test'),
        'payload' => ['type' => 'TRANSACTION'],
        'status' => 'processed',
    ]);
    $old->update(['created_at' => now()->subDays(15)]);

    $this->artisan('paymob:prune-webhooks', ['--days' => 30])
        ->expectsOutputToContain('Deleted 0')
        ->assertExitCode(0);

    expect(WebhookCall::find($old->id))->not->toBeNull();
});

it('does not delete non-processed webhook calls', function () {
    $table = config('paymob.tables.webhook_calls');
    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($t) {
            $t->uuid('id')->primary();
            $t->string('type', 40)->index();
            $t->string('external_id')->nullable()->index();
            $t->string('event')->nullable();
            $t->string('signature', 160)->nullable();
            $t->boolean('valid_signature')->default(false);
            $t->string('payload_hash', 64)->unique();
            $t->longText('payload');
            $t->string('status', 30)->default('pending')->index();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    $pending = WebhookCall::create([
        'type' => 'transaction',
        'payload_hash' => hash('sha256', 'old_pending'),
        'payload' => ['type' => 'TRANSACTION'],
        'status' => 'pending',
    ]);
    $pending->update(['created_at' => now()->subDays(100)]);

    $this->artisan('paymob:prune-webhooks', ['--days' => 90])
        ->expectsOutputToContain('Deleted 0')
        ->assertExitCode(0);

    expect(WebhookCall::find($pending->id))->not->toBeNull();
});

it('does not delete recent processed webhook calls', function () {
    $table = config('paymob.tables.webhook_calls');
    if (! Schema::hasTable($table)) {
        Schema::create($table, function ($t) {
            $t->uuid('id')->primary();
            $t->string('type', 40)->index();
            $t->string('external_id')->nullable()->index();
            $t->string('event')->nullable();
            $t->string('signature', 160)->nullable();
            $t->boolean('valid_signature')->default(false);
            $t->string('payload_hash', 64)->unique();
            $t->longText('payload');
            $t->string('status', 30)->default('pending')->index();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->text('error')->nullable();
            $t->timestamp('processed_at')->nullable();
            $t->timestamps();
        });
    }

    $recent = WebhookCall::create([
        'type' => 'transaction',
        'payload_hash' => hash('sha256', 'recent_processed'),
        'payload' => ['type' => 'TRANSACTION'],
        'status' => 'processed',
    ]);
    $recent->update(['created_at' => now()->subDays(10)]);

    $this->artisan('paymob:prune-webhooks', ['--days' => 90])
        ->expectsOutputToContain('Deleted 0')
        ->assertExitCode(0);

    expect(WebhookCall::find($recent->id))->not->toBeNull();
});
