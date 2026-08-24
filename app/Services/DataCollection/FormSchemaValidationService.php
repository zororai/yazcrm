<?php

namespace App\Services\DataCollection;

use App\Support\DataCollection\ConditionEvaluator;
use App\Support\DataCollection\QuestionType;

class FormSchemaValidationService
{
    // Returns a list of human-readable error strings; empty array means the
    // schema is publishable. Never trusts the frontend's own validation.
    public function validate(array $schema): array
    {
        $errors = [];
        $sections = $schema['sections'] ?? [];

        if (empty($sections)) {
            $errors[] = 'The form has no sections.';

            return $errors;
        }

        $questionIds = [];
        $totalQuestions = 0;

        foreach ($sections as $sectionIndex => $section) {
            $questions = $section['questions'] ?? [];
            $totalQuestions += count($questions);

            foreach ($questions as $question) {
                $id = $question['id'] ?? null;
                $type = $question['type'] ?? null;

                if (! $id) {
                    $errors[] = "Section ".($sectionIndex + 1).' has a question with no ID.';
                    continue;
                }

                if (in_array($id, $questionIds, true)) {
                    $errors[] = "Duplicate question ID '{$id}'.";
                } else {
                    $questionIds[] = $id;
                }

                if (! in_array($type, QuestionType::ALL, true)) {
                    $errors[] = "Question '{$id}' has an unsupported type.";
                }

                if (in_array($type, QuestionType::REQUIRES_OPTIONS, true) && empty($question['options'])) {
                    $errors[] = "Question '{$id}' is a {$type} question but has no options.";
                }
            }
        }

        if ($totalQuestions === 0) {
            $errors[] = 'The form has no questions.';
        }

        // Second pass: conditional logic can reference a question defined
        // anywhere in the form, so it's checked once all IDs are known.
        foreach ($sections as $section) {
            foreach ($section['questions'] ?? [] as $question) {
                $condition = $question['visible_if'] ?? null;
                if (! $condition) {
                    continue;
                }

                $id = $question['id'] ?? '?';
                $targetId = $condition['question'] ?? null;
                $operator = $condition['operator'] ?? null;

                if (! $targetId || ! in_array($targetId, $questionIds, true)) {
                    $errors[] = "Question '{$id}' has conditional logic referencing a non-existent question.";
                }

                if (! in_array($operator, ConditionEvaluator::OPERATORS, true)) {
                    $errors[] = "Question '{$id}' has conditional logic with an invalid operator.";
                }

                if ($targetId === $id) {
                    $errors[] = "Question '{$id}' cannot have conditional logic that depends on itself.";
                }
            }
        }

        return $errors;
    }
}
