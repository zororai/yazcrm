<?php

namespace App\Services\Leave;

use App\Models\Leave\PublicHoliday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

// Leave is counted in working days: Monday to Friday, minus public holidays.
class WorkingDays
{
    public static function count(CarbonInterface|string $start, CarbonInterface|string $end): int
    {
        return count(self::dates($start, $end));
    }

    /** @return list<string> Y-m-d dates in the range that are working days. */
    public static function dates(CarbonInterface|string $start, CarbonInterface|string $end): array
    {
        $start = CarbonImmutable::parse($start)->startOfDay();
        $end   = CarbonImmutable::parse($end)->startOfDay();

        if ($end->lt($start)) {
            return [];
        }

        $holidays = PublicHoliday::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->pluck('date')
            ->map(fn ($d) => $d->toDateString())
            ->flip();

        $dates = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            if (! $day->isWeekend() && ! isset($holidays[$day->toDateString()])) {
                $dates[] = $day->toDateString();
            }
        }

        return $dates;
    }
}
