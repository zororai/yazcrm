<?php

namespace App\Services\DataCollection;

use App\Support\DataCollection\QuestionType;

class SubmissionValidationService
{
    // Never trust the frontend — every submission is revalidated here against
    // its exact form-version schema before it's allowed to move to 'submitted'.
    public function validate(array $schema, array $answers): array
    {
        $errors = [];

        foreach ($schema['sections'] ?? [] as $section) {
            foreach ($section['questions'] ?? [] as $question) {
                $id    = $question['id'] ?? null;
                $type  = $question['type'] ?? null;
                $label = $question['label'] ?? $id;
                $value = $answers[$id] ?? null;
                $empty = $value === null || $value === '' || $value === [];

                if (! empty($question['required']) && $empty) {
                    $errors[$id] = "\"{$label}\" is required.";
                    continue;
                }

                if ($empty) {
                    continue;
                }

                $error = $this->validateType($type, $value, $question);
                if ($error) {
                    $errors[$id] = "\"{$label}\" {$error}";
                }
            }
        }

        return $errors;
    }

    private function validateType(?string $type, mixed $value, array $question): ?string
    {
        return match ($type) {
            QuestionType::NUMBER, QuestionType::DECIMAL, QuestionType::INTEGER =>
                is_numeric($value) ? null : 'must be a number.',
            QuestionType::EMAIL =>
                filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'must be a valid email address.',
            QuestionType::DATE, QuestionType::DATETIME =>
                strtotime((string) $value) !== false ? null : 'must be a valid date.',
            QuestionType::SELECT_ONE =>
                in_array($value, array_column($question['options'] ?? [], 'value'), true) ? null : 'has an invalid selection.',
            QuestionType::SELECT_MULTIPLE => (function () use ($value, $question) {
                if (! is_array($value)) {
                    return 'must be a list of selections.';
                }
                $valid = array_column($question['options'] ?? [], 'value');

                return array_diff($value, $valid) ? 'has an invalid selection.' : null;
            })(),
            QuestionType::YES_NO =>
                in_array($value, [true, false, 'yes', 'no', '1', '0', 1, 0], true) ? null : 'must be yes or no.',
            default => null,
        };
    }
}
