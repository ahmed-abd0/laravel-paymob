<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('paymob.tables.webhook_calls', 'paymob_webhook_calls'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40)->index();
            $table->string('external_id')->nullable()->index();
            $table->string('event')->nullable();
            $table->string('signature', 160)->nullable();
            $table->boolean('valid_signature')->default(false);
            $table->string('payload_hash', 64)->unique();
            $table->longText('payload');
            $table->string('status', 30)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('paymob.tables.webhook_calls', 'paymob_webhook_calls'));
    }
};
