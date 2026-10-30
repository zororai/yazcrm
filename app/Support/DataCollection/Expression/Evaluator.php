<?php

namespace App\Support\DataCollection\Expression;

use Carbon\CarbonImmutable;

// Evaluates a Parser AST with XPath 1.0 / ODK semantics, close enough that
// the server agrees with the device on relevant, required and constraint:
//  - an empty answer is the empty string; as a number it is NaN, so
//    ${age} > 18 is false while age is blank;
//  - = compares as numbers if either side is a number, as booleans if either
//    side is boolean, otherwise as strings;
//  - <, >, <=, >= always compare as numbers; dates count days since
//    1970-01-01 (so today() - ${dob} is an age in days);
//  - select_multiple answers are arrays here (space-separated in XPath).
// Never throws for data: bad input gives NaN/''/false, like the device.
class Evaluator
{
    public static function evaluate(array $ast, Context $ctx): mixed
    {
        return match ($ast[0]) {
            'num'  => $ast[1],
            'str'  => $ast[1],
            'ref'  => $ctx->resolve($ast[1]),
            'self' => $ctx->current,
            'col'  => $ctx->choice[$ast[1]] ?? null,
            'neg'  => -self::number(self::evaluate($ast[1], $ctx)),
            'bin'  => self::binary($ast[1], $ast[2], $ast[3], $ctx),
            'call' => self::call($ast[1], $ast[2], $ctx),
        };
    }

    public static function truthy(array $ast, Context $ctx): bool
    {
        return self::boolean(self::evaluate($ast, $ctx));
    }

    // ── Conversions ─────────────────────────────────────────────────────────

    public static function number(mixed $v): float
    {
        if ($v instanceof Nodeset) {
            $v = $v->first();
        }
        if (is_bool($v)) {
            return $v ? 1.0 : 0.0;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_array($v)) {
            return count($v) === 1 ? self::number(reset($v)) : NAN;
        }

        $s = trim((string) $v);
        if ($s === '') {
            return NAN;
        }
        if (is_numeric($s)) {
            return (float) $s;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $s, 'UTC');

            return $d ? floor($d->getTimestamp() / 86400) : NAN;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $s)) {
            try {
                return CarbonImmutable::parse($s)->getTimestamp() / 86400;
            } catch (\Throwable) {
                return NAN;
            }
        }

        return NAN;
    }

    public static function string(mixed $v): string
    {
        if ($v instanceof Nodeset) {
            $v = $v->first();
        }
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_float($v)) {
            if (is_nan($v)) {
                return 'NaN';
            }

            return floor($v) == $v && abs($v) < 1e15 ? (string) (int) $v : rtrim(rtrim(sprintf('%.10F', $v), '0'), '.');
        }
        if (is_array($v)) {
            // geopoint {lat,lng,accuracy} → "lat lng 0 accuracy", as on ODK
            if (array_key_exists('lat', $v) || array_key_exists('lng', $v)) {
                return trim(($v['lat'] ?? '').' '.($v['lng'] ?? '').' 0 '.($v['accuracy'] ?? ''));
            }

            return implode(' ', array_map(fn ($x) => is_array($x) ? '' : self::string($x), $v));
        }

        return (string) $v;
    }

    public static function boolean(mixed $v): bool
    {
        if ($v instanceof Nodeset) {
            return $v->items !== [];
        }
        if (is_bool($v)) {
            return $v;
        }
        if (is_int($v) || is_float($v)) {
            return $v != 0 && ! is_nan((float) $v);
        }
        if (is_array($v)) {
            return $v !== [];
        }

        return $v !== null && $v !== '';
    }

    // ── Operators ───────────────────────────────────────────────────────────

    private static function binary(string $op, array $l, array $r, Context $ctx): mixed
    {
        // and/or short-circuit, as on the device.
        if ($op === 'and') {
            return self::truthy($l, $ctx) && self::truthy($r, $ctx);
        }
        if ($op === 'or') {
            return self::truthy($l, $ctx) || self::truthy($r, $ctx);
        }

        $a = self::evaluate($l, $ctx);
        $b = self::evaluate($r, $ctx);

        return match ($op) {
            '='   => self::equals($a, $b),
            '!='  => ! self::equals($a, $b),
            '<'   => self::number($a) < self::number($b),
            '<='  => self::number($a) <= self::number($b),
            '>'   => self::number($a) > self::number($b),
            '>='  => self::number($a) >= self::number($b),
            '+'   => self::number($a) + self::number($b),
            '-'   => self::number($a) - self::number($b),
            '*'   => self::number($a) * self::number($b),
            'div' => fdiv(self::number($a), self::number($b)),
            'mod' => fmod(self::number($a), self::number($b)),
        };
    }

    private static function equals(mixed $a, mixed $b): bool
    {
        // A nodeset equals a value if any of its items does.
        if ($a instanceof Nodeset || $b instanceof Nodeset) {
            [$set, $other] = $a instanceof Nodeset ? [$a, $b] : [$b, $a];
            foreach ($set->items as $item) {
                if (self::equals($item, $other)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($a) || is_bool($b)) {
            return self::boolean($a) === self::boolean($b);
        }
        if (is_int($a) || is_float($a) || is_int($b) || is_float($b)) {
            $x = self::number($a);
            $y = self::number($b);

            return ! is_nan($x) && ! is_nan($y) && $x == $y;
        }

        return self::string($a) === self::string($b);
    }

    // ── Functions ───────────────────────────────────────────────────────────

    private static function call(string $name, array $args, Context $ctx): mixed
    {
        // Lazy: only the chosen branch is evaluated.
        if ($name === 'if') {
            return self::truthy($args[0], $ctx) ? self::evaluate($args[1], $ctx) : self::evaluate($args[2], $ctx);
        }

        $v = array_map(fn ($a) => self::evaluate($a, $ctx), $args);

        return match ($name) {
            'selected'          => in_array(self::string($v[1]), self::choices($v[0]), true),
            'count-selected'    => (float) count(self::choices($v[0])),
            'selected-at'       => self::choices($v[0])[(int) self::number($v[1])] ?? '',
            'not'               => ! self::boolean($v[0]),
            'true'              => true,
            'false'             => false,
            'boolean'           => self::boolean($v[0]),
            'number'            => self::number($v[0]),
            'string'            => self::string($v[0]),
            'int'               => self::toInt(self::number($v[0])),
            'round'             => self::round(self::number($v[0]), isset($v[1]) ? (int) self::number($v[1]) : 0),
            'floor'             => floor(self::number($v[0])),
            'ceiling'           => ceil(self::number($v[0])),
            'abs'               => abs(self::number($v[0])),
            'pow'               => self::number($v[0]) ** self::number($v[1]),
            'sqrt'              => sqrt(self::number($v[0])),
            'min'               => self::aggregate($v, 'min'),
            'max'               => self::aggregate($v, 'max'),
            'sum'               => array_sum(array_map([self::class, 'number'], self::items($v[0]))),
            'count'             => (float) count(self::items($v[0])),
            'today'             => CarbonImmutable::now()->toDateString(),
            'now'               => CarbonImmutable::now()->toIso8601String(),
            'date'              => self::date($v[0]),
            'decimal-date-time' => self::number($v[0]),
            'concat'            => implode('', array_map(fn ($x) => $x instanceof Nodeset ? implode('', array_map([self::class, 'string'], $x->items)) : self::string($x), $v)),
            'join'              => implode(self::string($v[0]), array_map([self::class, 'string'], array_merge(...array_map([self::class, 'items'], array_slice($v, 1))))),
            'contains'          => str_contains(self::string($v[0]), self::string($v[1])),
            'starts-with'       => str_starts_with(self::string($v[0]), self::string($v[1])),
            'ends-with'         => str_ends_with(self::string($v[0]), self::string($v[1])),
            'substr'            => self::substr(self::string($v[0]), (int) self::number($v[1]), isset($v[2]) ? (int) self::number($v[2]) : null),
            'substring-before'  => ($p = mb_strpos(self::string($v[0]), self::string($v[1]))) === false ? '' : mb_substr(self::string($v[0]), 0, $p),
            'substring-after'   => ($p = mb_strpos(self::string($v[0]), self::string($v[1]))) === false ? '' : mb_substr(self::string($v[0]), $p + mb_strlen(self::string($v[1]))),
            'string-length'     => (float) mb_strlen(self::string($v[0] ?? $ctx->current)),
            'normalize-space'   => trim(preg_replace('/\s+/u', ' ', self::string($v[0] ?? $ctx->current))),
            'regex'             => self::regex(self::string($v[0]), self::string($v[1])),
            'coalesce'          => self::boolean($v[0]) ? $v[0] : $v[1],
            'once'              => $v[0],
            default             => throw new ExpressionException("Unknown function '{$name}()'."),
        };
    }

    // select_multiple: array of names; also tolerates the XPath string form.
    private static function choices(mixed $v): array
    {
        if ($v instanceof Nodeset) {
            $v = $v->first();
        }
        if (is_array($v)) {
            return array_values(array_map(fn ($x) => self::string($x), array_filter($v, fn ($x) => ! is_array($x))));
        }
        $s = trim(self::string($v));

        return $s === '' ? [] : preg_split('/\s+/', $s);
    }

    private static function items(mixed $v): array
    {
        if ($v instanceof Nodeset) {
            return $v->items;
        }

        return $v === null || $v === '' ? [] : [$v];
    }

    private static function aggregate(array $values, string $fn): float
    {
        $numbers = [];
        foreach ($values as $value) {
            foreach (self::items($value) as $item) {
                $numbers[] = self::number($item);
            }
        }
        if (! $numbers || array_filter($numbers, 'is_nan')) {
            return NAN;
        }

        return $fn(...$numbers);
    }

    private static function toInt(float $n): float
    {
        return is_nan($n) || is_infinite($n) ? $n : (float) (int) $n;
    }

    private static function round(float $n, int $digits): float
    {
        return is_nan($n) ? NAN : round($n, $digits);
    }

    private static function date(mixed $v): string
    {
        $n = self::number($v);
        if (is_nan($n)) {
            return '';
        }
        if (is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}/', $v)) {
            return substr($v, 0, 10);
        }

        return CarbonImmutable::createFromTimestampUTC((int) floor($n) * 86400)->toDateString();
    }

    // ODK substr: 0-based start, exclusive end.
    private static function substr(string $s, int $start, ?int $end): string
    {
        $len = mb_strlen($s);
        $start = max(0, $start);
        $end = $end === null ? $len : min($len, $end);

        return $end > $start ? mb_substr($s, $start, $end - $start) : '';
    }

    private static function regex(string $value, string $pattern): bool
    {
        $delimited = '/'.str_replace('/', '\/', $pattern).'/u';

        return @preg_match($delimited, $value) === 1;
    }
}
