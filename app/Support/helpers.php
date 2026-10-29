<?php

use Carbon\CarbonInterface;

if (! function_exists('money')) {
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $amount = (float) $amount;
        $currency ??= config('partner.currency');
        $symbol = $currency === 'USD' ? '$' : '';
        $formatted = $symbol.number_format(abs($amount), 2);

        return ($amount < 0 ? '−' : '').($symbol === '' ? "{$formatted} {$currency}" : $formatted);
    }
}

if (! function_exists('short_date')) {
    function short_date(?CarbonInterface $date, string $empty = '—'): string
    {
        return $date ? $date->format('M j, Y') : $empty;
    }
}
