<?php

namespace Paymob\Laravel\Enums;

enum Region: string
{
    case EGYPT = 'egypt';
    case KSA = 'ksa';
    case UAE = 'uae';
    case OMAN = 'oman';

    public function baseUrl(): string
    {
        return match ($this) {
            self::EGYPT => 'https://accept.paymob.com',
            self::KSA => 'https://ksa.paymob.com',
            self::UAE => 'https://uae.paymob.com',
            self::OMAN => 'https://oman.paymob.com'
        };
    }
}
