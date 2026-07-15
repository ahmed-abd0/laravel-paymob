<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WebhookCall extends Model
{
    use HasUuids;

    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['valid_signature' => 'boolean', 'payload' => 'encrypted:array', 'processed_at' => 'datetime'];
    }

    public function getTable(): string
    {
        return config('paymob.tables.webhook_calls', parent::getTable());
    }
}
