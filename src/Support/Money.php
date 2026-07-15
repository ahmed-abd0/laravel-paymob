<?php

namespace Paymob\Laravel\Support;

use InvalidArgumentException;

final class Money
{
    public static function cents(int|float|string $amount): int
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Amount must be numeric.');
        }

        return (int) round(((float) $amount) * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
