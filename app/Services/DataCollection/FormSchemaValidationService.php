<?php

namespace App\Services\DataCollection;

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

        return $errors;
    }
}
