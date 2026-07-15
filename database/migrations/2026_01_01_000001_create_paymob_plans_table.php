<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('paymob.tables.plans', 'paymob_plans'), function (Blueprint $table) {
            $table->id();
            $table->string('paymob_id')->unique();
            $table->string('name');
            $table->unsignedSmallInteger('frequency');
            $table->string('plan_type', 40)->default('rent');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('EGP');
            $table->unsignedInteger('integration_id');
            $table->unsignedSmallInteger('reminder_days')->default(2);
            $table->unsignedSmallInteger('retrial_days')->default(2);
            $table->unsignedInteger('number_of_deductions')->nullable();
            $table->boolean('use_transaction_amount')->default(false);
            $table->boolean('active')->default(true);
            $table->string('webhook_url', 1000)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('paymob.tables.plans', 'paymob_plans'));
    }
};
