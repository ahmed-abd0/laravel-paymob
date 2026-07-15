<?php

namespace Paymob\Laravel\Events;

use Paymob\Laravel\Models\Transaction;

final class TransactionUpdated
{
    public function __construct(public readonly Transaction $transaction) {}
}
