<?php

namespace App\Support;

class RiskScorer
{
    public static function residual(int $inherent, ?string $best): int
    {
        $f = match ($best) {
            'high'   => 0.4,
            'medium' => 0.6,
            'low'    => 0.8,
            default  => 1.0,
        };

        return max(1, (int) round($inherent * $f));
    }

    public static function band(int $score): string
    {
        return $score >= 15 ? 'red' : ($score >= 7 ? 'amber' : 'green');
    }
}
