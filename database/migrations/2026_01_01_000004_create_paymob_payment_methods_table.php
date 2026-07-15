<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('paymob.tables.payment_methods', 'paymob_payment_methods'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained(config('paymob.tables.subscriptions', 'paymob_subscriptions'))->nullOnDelete();
            $table->nullableMorphs('billable');
            $table->string('paymob_id')->nullable()->unique();
            $table->text('token');
            $table->string('token_hash', 64)->unique();
            $table->string('masked_pan')->nullable();
            $table->string('brand')->nullable();
            $table->boolean('primary')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists(config('paymob.tables.payment_methods', 'paymob_payment_methods')); }
};
