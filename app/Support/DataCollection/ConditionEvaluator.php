<?php

namespace App\Support\DataCollection;

class ConditionEvaluator
{
    public const OPERATORS = [
        'equals', 'not_equals', 'greater_than', 'less_than',
        'greater_than_or_equal', 'less_than_or_equal', 'contains',
        'is_empty', 'is_not_empty',
    ];

    // A question with no visible_if is always visible. Otherwise, evaluate
    // its condition against the answer already given for the target question.
    public static function isVisible(array $question, array $answers): bool
    {
        $condition = $question['visible_if'] ?? null;
        if (! $condition) {
            return true;
        }

        $target = $answers[$condition['question'] ?? ''] ?? null;
        $value  = $condition['value'] ?? null;

        return match ($condition['operator'] ?? 'equals') {
            'equals'                 => self::loose($target, $value),
            'not_equals'             => ! self::loose($target, $value),
            'greater_than'           => is_numeric($target) && is_numeric($value) && $target > $value,
            'less_than'              => is_numeric($target) && is_numeric($value) && $target < $value,
            'greater_than_or_equal'  => is_numeric($target) && is_numeric($value) && $target >= $value,
            'less_than_or_equal'     => is_numeric($target) && is_numeric($value) && $target <= $value,
            'contains'               => is_array($target) ? in_array($value, $target, true) : str_contains((string) $target, (string) $value),
            'is_empty'               => $target === null || $target === '' || $target === [],
            'is_not_empty'           => ! ($target === null || $target === '' || $target === []),
            default                  => true,
        };
    }

    private static function loose(mixed $a, mixed $b): bool
    {
        return (string) $a === (string) $b;
    }
}
