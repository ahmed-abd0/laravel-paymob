<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('paymob.tables.transactions', 'paymob_transactions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained(config('paymob.tables.subscriptions', 'paymob_subscriptions'))->nullOnDelete();
            $table->nullableMorphs('billable');
            $table->string('paymob_id')->unique();
            $table->string('order_id')->nullable()->index();
            $table->string('merchant_order_id')->nullable()->index();
            $table->string('intention_id')->nullable()->index();
            $table->string('parent_transaction_id')->nullable();
            $table->string('status', 40)->index();
            $table->unsignedBigInteger('amount_cents')->default(0);
            $table->unsignedBigInteger('refunded_amount_cents')->default(0);
            $table->unsignedBigInteger('captured_amount_cents')->default(0);
            $table->string('currency', 3)->default('EGP');
            $table->unsignedInteger('integration_id')->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_subtype')->nullable();
            $table->string('source_pan')->nullable();
            $table->boolean('success')->default(false);
            $table->boolean('pending')->default(false);
            $table->boolean('is_refund')->default(false);
            $table->boolean('is_void')->default(false);
            $table->boolean('is_capture')->default(false);
            $table->timestamp('occurred_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('paymob.tables.transactions', 'paymob_transactions'));
    }
};
