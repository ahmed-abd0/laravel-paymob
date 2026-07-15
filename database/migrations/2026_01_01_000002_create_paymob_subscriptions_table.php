<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('paymob.tables.subscriptions', 'paymob_subscriptions'), function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('billable');
            $table->foreignId('paymob_plan_id')->nullable()->constrained(config('paymob.tables.plans', 'paymob_plans'))->nullOnDelete();
            $table->string('name')->default('default');
            $table->string('paymob_id')->nullable()->unique();
            $table->string('remote_plan_id')->index();
            $table->uuid('reference')->unique();
            $table->string('intention_id')->nullable()->index();
            $table->string('intention_order_id')->nullable()->index();
            $table->text('client_secret')->nullable();
            $table->string('status', 40)->default('incomplete')->index();
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('EGP');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('next_billing_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['billable_type', 'billable_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('paymob.tables.subscriptions', 'paymob_subscriptions'));
    }
};
