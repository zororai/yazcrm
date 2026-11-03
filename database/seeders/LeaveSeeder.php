<?php

namespace Database\Seeders;

use App\Models\Leave\LeaveType;
use App\Models\Leave\PublicHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

// Default leave types and Zimbabwe public holidays for this year and next.
// Safe to re-run: existing types and dates are left as they are, so an
// admin's edits are kept.
//   php artisan db:seed --class=LeaveSeeder
class LeaveSeeder extends Seeder
{
    // Labour Act defaults; adjust per contract on the Entitlements page.
    private const TYPES = [
        ['name' => 'Annual',        'default_days' => 22, 'requires_attachment' => false, 'is_paid' => true],
        ['name' => 'Sick',          'default_days' => 90, 'requires_attachment' => true,  'is_paid' => true],
        ['name' => 'Maternity',     'default_days' => 98, 'requires_attachment' => true,  'is_paid' => true],
        ['name' => 'Paternity',     'default_days' => 5,  'requires_attachment' => false, 'is_paid' => true],
        ['name' => 'Compassionate', 'default_days' => 5,  'requires_attachment' => false, 'is_paid' => true],
        ['name' => 'Unpaid',        'default_days' => 0,  'requires_attachment' => false, 'is_paid' => false],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            LeaveType::firstOrCreate(['name' => $type['name']], $type + ['is_active' => true]);
        }

        $year = now()->year;
        foreach ([$year, $year + 1] as $y) {
            foreach (self::zimbabweHolidays($y) as $date => $name) {
                PublicHoliday::firstOrCreate(['date' => $date], ['name' => $name]);
            }
        }
    }

    /**
     * Gazetted Zimbabwe public holidays for a year. A holiday that falls on a
     * Sunday is observed on the next day that is not already a holiday.
     *
     * @return array<string, string> Y-m-d => name
     */
    public static function zimbabweHolidays(int $year): array
    {
        $easter = self::easterSunday($year);
        $heroes = CarbonImmutable::create($year, 8, 1)->nthOfMonth(2, CarbonImmutable::MONDAY);

        $fixed = [
            [CarbonImmutable::create($year, 1, 1),   "New Year's Day"],
            [CarbonImmutable::create($year, 2, 21),  'Robert Gabriel Mugabe National Youth Day'],
            [$easter->subDays(2),                    'Good Friday'],
            [$easter->subDay(),                      'Easter Saturday'],
            [$easter->addDay(),                      'Easter Monday'],
            [CarbonImmutable::create($year, 4, 18),  'Independence Day'],
            [CarbonImmutable::create($year, 5, 1),   "Workers' Day"],
            [CarbonImmutable::create($year, 5, 25),  'Africa Day'],
            [$heroes,                                "Heroes' Day"],
            [$heroes->addDay(),                      'Defence Forces Day'],
            [CarbonImmutable::create($year, 12, 22), 'National Unity Day'],
            [CarbonImmutable::create($year, 12, 25), 'Christmas Day'],
            [CarbonImmutable::create($year, 12, 26), 'Boxing Day'],
        ];

        $holidays = [];
        foreach ($fixed as [$date, $name]) {
            $holidays[$date->toDateString()] = $name;
        }

        foreach ($fixed as [$date, $name]) {
            if (! $date->isSunday()) {
                continue;
            }
            $observed = $date->addDay();
            while (isset($holidays[$observed->toDateString()])) {
                $observed = $observed->addDay();
            }
            $holidays[$observed->toDateString()] = "{$name} (observed)";
        }

        ksort($holidays);

        return $holidays;
    }

    // Anonymous Gregorian algorithm (no ext-calendar needed).
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
