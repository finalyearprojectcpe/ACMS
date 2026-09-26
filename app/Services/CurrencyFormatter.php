<?php

namespace App\Services;

class CurrencyFormatter
{
    public function nairaFromKobo(int $amountKobo): string
    {
        return '₦'.number_format($amountKobo / 100, 2);
    }
}
