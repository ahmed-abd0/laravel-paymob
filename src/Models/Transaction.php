<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Paymob\Laravel\Enums\TransactionStatus;

class Transaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'amount_cents' => 'integer',
            'refunded_amount_cents' => 'integer',
            'captured_amount_cents' => 'integer',
            'integration_id' => 'integer',
            'success' => 'boolean',
            'pending' => 'boolean',
            'is_refund' => 'boolean',
            'is_void' => 'boolean',
            'is_capture' => 'boolean',
            'occurred_at' => 'datetime',
            'payload' => 'array'
        ];
    }

    public function getTable(): string
    {
        return config('paymob.tables.transactions', parent::getTable());
    }
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('paymob.models.subscription'));
    }
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
    public function succeeded(): bool
    {
        return $this->status === TransactionStatus::SUCCEEDED;
    }
    public function failed(): bool
    {
        return $this->status === TransactionStatus::FAILED;
    }
}
