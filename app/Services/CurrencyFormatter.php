<?php

namespace App\Services;

class CurrencyFormatter
{
    public function nairaFromKobo(int $amountKobo): string
    {
        return 'NGN '.number_format($amountKobo / 100, 2);
    }
}
