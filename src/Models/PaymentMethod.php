<?php

namespace Paymob\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentMethod extends Model
{
    protected $guarded = [];
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'primary' => 'boolean', 'expires_at' => 'datetime', 'payload' => 'array'];
    }

    public function getTable(): string
    {
        return config('paymob.tables.payment_methods', parent::getTable());
    }
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(config('paymob.models.subscription'));
    }
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }
}
