<?php

namespace App\Services\DataCollection;

use App\Support\DataCollection\Expression\Context;
use App\Support\DataCollection\Expression\Evaluator;
use App\Support\DataCollection\Expression\Parser;
use App\Support\DataCollection\SchemaWalker;
use Illuminate\Support\Facades\Log;
use Throwable;

// Checks a submission's data against the exact form version it was filled
// in on: required, types, choice values, constraints and repeats, with
// relevant evaluated first, so a question hidden by skip logic is never
// required. Returns field errors [{field, message}] plus a completion %.
//
// Expression problems at runtime count in the submitter's favour (hidden,
// not required, constraint passes): rejecting real data because of a form
// bug would be worse than accepting it.
class SubmissionValidator
{
    private array $schema;
    private array $errors;
    private array $media;
    private int $visible;
    private int $answered;

    // $media: field name => list of filenames declared in the item's media[].
    public function validate(array $schema, array $data, array $media = []): array
    {
        $this->schema = $schema;
        $this->errors = [];
        $this->media = $media;
        $this->visible = 0;
        $this->answered = 0;

        $this->nodes($schema['children'] ?? [], $data, SchemaWalker::context($schema, $data), '');

        return [
            'errors'     => $this->errors,
            'completion' => $this->visible ? (int) round($this->answered / $this->visible * 100) : 100,
        ];
    }

    private function nodes(array $nodes, array $scope, Context $ctx, string $prefix): void
    {
        foreach ($nodes as $node) {
            $type = $node['type'] ?? null;
            $name = $node['name'] ?? null;
            if (! $name || ! $this->relevant($node, $ctx)) {
                continue;
            }

            if ($type === 'group') {
                $this->nodes($node['children'] ?? [], $scope, $ctx, $prefix);
                continue;
            }

            if ($type === 'repeat') {
                $this->repeat($node, $scope[$name] ?? null, $ctx, $prefix.$name);
                continue;
            }

            if (in_array($type, ['note', 'calculate'], true)) {
                continue;
            }

            $this->field($node, $scope, $ctx, $prefix.$name);
        }
    }

    private function repeat(array $node, mixed $instances, Context $ctx, string $path): void
    {
        if ($instances === null || $instances === []) {
            return;
        }
        if (! is_array($instances) || ! array_is_list($instances)) {
            $this->error($path, 'A repeat must be a list of entries.');

            return;
        }

        foreach ($instances as $i => $instance) {
            if (! is_array($instance) || ($instance !== [] && array_is_list($instance))) {
                $this->error("{$path}[{$i}]", 'Each repeat entry must be an object of answers.');
                continue;
            }
            $this->nodes($node['children'] ?? [], $instance, $ctx->enter($instance), "{$path}[{$i}].");
        }
    }

    private function field(array $node, array $scope, Context $ctx, string $path): void
    {
        $value = $scope[$node['name']] ?? null;
        $empty = $value === null || $value === '' || $value === [];

        $this->visible++;
        if (! $empty) {
            $this->answered++;
        }

        if ($empty) {
            if ($this->required($node, $ctx)) {
                $this->error($path, SchemaWalker::label($node['required_message'] ?? null) ?: 'This field is required.');
            }

            return;
        }

        $typeError = $this->checkType($node, $value);
        if ($typeError) {
            $this->error($path, $typeError);

            return;
        }

        if (isset($node['constraint']) && ! $this->check($node['constraint'], $ctx->withCurrent($value), true, $node)) {
            $this->error($path, SchemaWalker::label($node['constraint_message'] ?? null) ?: 'This answer is not allowed by the form.');
        }
    }

    private function checkType(array $node, mixed $value): ?string
    {
        $name = $node['name'];

        switch ($node['type']) {
            case 'text':
                return is_string($value) || is_int($value) || is_float($value) ? null : 'Must be text.';

            case 'integer':
                return is_int($value) || (is_float($value) && floor($value) == $value) ? null : 'Must be a whole number.';

            case 'decimal':
                return is_int($value) || is_float($value) ? null : 'Must be a number.';

            case 'select_one':
                if (! is_string($value) && ! is_int($value)) {
                    return 'Must be one choice name.';
                }

                return in_array((string) $value, $this->choiceNames($node), true) ? null : "'{$value}' is not one of the choices.";

            case 'select_multiple':
                if (! is_array($value) || ! array_is_list($value)) {
                    return 'Must be a list of choice names.';
                }
                $names = $this->choiceNames($node);
                foreach ($value as $choice) {
                    if (! is_string($choice) && ! is_int($choice)) {
                        return 'Must be a list of choice names.';
                    }
                    if (! in_array((string) $choice, $names, true)) {
                        return "'{$choice}' is not one of the choices.";
                    }
                }

                return null;

            case 'date':
                return is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    ? null : 'Must be a date as YYYY-MM-DD.';

            case 'time':
                return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/', $value)
                    ? null : 'Must be a time as HH:MM:SS.';

            case 'datetime':
                if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T([01]\d|2[0-3]):[0-5]\d(:[0-5]\d(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})$/', $value)) {
                    return 'Must be an ISO 8601 date and time with a UTC offset.';
                }
                try {
                    new \DateTimeImmutable($value);
                } catch (Throwable) {
                    return 'Must be an ISO 8601 date and time with a UTC offset.';
                }

                return null;

            case 'geopoint':
                if (! is_array($value) || ! isset($value['lat'], $value['lng'])
                    || ! is_numeric($value['lat']) || ! is_numeric($value['lng'])
                    || abs((float) $value['lat']) > 90 || abs((float) $value['lng']) > 180
                    || (isset($value['accuracy']) && ! is_numeric($value['accuracy']))) {
                    return 'Must be a location {"lat", "lng", "accuracy"}.';
                }

                return null;

            case 'photo':
                if (! is_string($value)) {
                    return 'Must be the photo\'s filename.';
                }

                return in_array($value, $this->media[$name] ?? [], true) ? null : "'{$value}' is not listed in media[] for this field.";
        }

        return null;
    }

    private function choiceNames(array $node): array
    {
        return array_map(fn ($c) => (string) $c['name'], SchemaWalker::choices($this->schema, $node));
    }

    private function relevant(array $node, Context $ctx): bool
    {
        return ! isset($node['relevant']) || $this->check($node['relevant'], $ctx, false, $node);
    }

    private function required(array $node, Context $ctx): bool
    {
        $required = $node['required'] ?? null;
        if ($required === null) {
            return false;
        }
        if ($required === 'yes') {
            return true;
        }

        return $this->check($required, $ctx, false, $node);
    }

    private function check(string $expression, Context $ctx, bool $onError, array $node): bool
    {
        try {
            return Evaluator::truthy(Parser::parse($expression), $ctx);
        } catch (Throwable $e) {
            Log::warning('Data collection: expression could not be evaluated', [
                'field' => $node['name'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return $onError;
        }
    }

    private function error(string $field, string $message): void
    {
        $this->errors[] = ['field' => $field, 'message' => $message];
    }
}
