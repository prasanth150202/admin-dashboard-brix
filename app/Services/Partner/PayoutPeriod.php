<?php

namespace App\Services\Partner;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Which payout window a transaction falls in, per config('partner.payout_schedule').
 * An estimate: the Partner API exposes transactions, not the payouts built from them.
 */
class PayoutPeriod
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end] dates, inclusive */
    public static function for(CarbonInterface $at, ?string $schedule = null): array
    {
        $day = CarbonImmutable::parse($at)->utc()->startOfDay();

        return match ($schedule ?? config('partner.payout_schedule')) {
            'monthly' => [$day->startOfMonth(), $day->endOfMonth()->startOfDay()],
            'weekly' => [$day->startOfWeek(CarbonInterface::MONDAY), $day->endOfWeek(CarbonInterface::SUNDAY)->startOfDay()],
            default => $day->day <= 15
                ? [$day->startOfMonth(), $day->setDay(15)]
                : [$day->setDay(16), $day->endOfMonth()->startOfDay()],
        };
    }

    public static function label(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->isSameMonth($end)
            ? $start->format('M j').' – '.$end->format('j, Y')
            : $start->format('M j').' – '.$end->format('M j, Y');
    }
}
