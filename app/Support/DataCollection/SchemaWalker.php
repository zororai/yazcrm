<?php

namespace App\Support\DataCollection;

use App\Support\DataCollection\Expression\Context;

// Reads the canonical form schema produced by XlsFormParser:
//   {title, id_string, version, settings, choices: {list: [{name,label,..}]},
//    children: [{type, name, label, hint, required, relevant, constraint,
//                constraint_message, calculation, default, appearance,
//                choice_filter, list_name, choices, pii, children}]}
// Groups only organise questions (their children's answers sit at the same
// level of the data); repeats hold an array of instance objects.
class SchemaWalker
{
    public const VALUE_TYPES = [
        'text', 'integer', 'decimal', 'select_one', 'select_multiple', 'date', 'time',
        'datetime', 'geopoint', 'photo', 'calculate',
    ];

    // Every answer-holding field, groups flattened, in form order. Fields
    // inside a repeat carry the repeat's name and a "repeat/child" path.
    public static function fields(array $schema): array
    {
        $out = [];
        self::walk($schema['children'] ?? [], function (array $node, ?string $repeat, string $path) use (&$out) {
            if (in_array($node['type'] ?? null, self::VALUE_TYPES, true)) {
                $out[] = [
                    'name'   => $node['name'],
                    'type'   => $node['type'],
                    'label'  => self::label($node['label'] ?? null) ?: $node['name'],
                    'repeat' => $repeat,
                    'path'   => $path,
                    'pii'    => ! empty($node['pii']),
                    'node'   => $node,
                ];
            }
        });

        return $out;
    }

    // Every repeat: name, label, parent repeat (or null) and its fields.
    public static function repeats(array $schema): array
    {
        $out = [];
        self::walk($schema['children'] ?? [], function (array $node, ?string $repeat, string $path) use (&$out) {
            if (($node['type'] ?? null) === 'repeat') {
                $out[$node['name']] = [
                    'name'   => $node['name'],
                    'label'  => self::label($node['label'] ?? null) ?: $node['name'],
                    'parent' => $repeat,
                ];
            }
        });

        return $out;
    }

    // name => node, for every named node including groups and repeats.
    public static function index(array $schema): array
    {
        $out = [];
        self::walk($schema['children'] ?? [], function (array $node) use (&$out) {
            $out[$node['name']] = $node;
        });

        return $out;
    }

    // An evaluation context for the submission's data under this schema.
    public static function context(array $schema, array $data): Context
    {
        $repeatOf = [];
        $repeatNames = [];
        self::walk($schema['children'] ?? [], function (array $node, ?string $repeat) use (&$repeatOf, &$repeatNames) {
            if ($repeat !== null) {
                $repeatOf[$node['name']] = $repeat;
            }
            if (($node['type'] ?? null) === 'repeat') {
                $repeatNames[$node['name']] = true;
            }
        });

        return Context::forData($data, $repeatOf, $repeatNames);
    }

    public static function choices(array $schema, array $node): array
    {
        if (isset($node['choices']) && is_array($node['choices'])) {
            return $node['choices'];
        }

        return $schema['choices'][$node['list_name'] ?? ''] ?? [];
    }

    // name => label for a select's choices.
    public static function choiceLabels(array $schema, array $node): array
    {
        $out = [];
        foreach (self::choices($schema, $node) as $choice) {
            $out[(string) $choice['name']] = self::label($choice['label'] ?? null) ?: (string) $choice['name'];
        }

        return $out;
    }

    // Labels may be per language: {"English (en)": "...", "Shona (sn)": "..."}.
    public static function label(mixed $label, ?string $language = null): string
    {
        if (is_array($label)) {
            if ($language && isset($label[$language])) {
                return (string) $label[$language];
            }
            if (isset($label['default'])) {
                return (string) $label['default'];
            }
            foreach ($label as $lang => $text) {
                if (str_starts_with(strtolower((string) $lang), 'english')) {
                    return (string) $text;
                }
            }

            return (string) (reset($label) ?: '');
        }

        return (string) ($label ?? '');
    }

    // Depth-first: $fn(node, enclosing repeat name or null, data path).
    private static function walk(array $nodes, callable $fn, ?string $repeat = null, string $prefix = ''): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node) || ! isset($node['name'])) {
                continue;
            }
            $path = $prefix.$node['name'];
            $fn($node, $repeat, $path);

            $type = $node['type'] ?? null;
            if ($type === 'group') {
                self::walk($node['children'] ?? [], $fn, $repeat, $prefix);
            } elseif ($type === 'repeat') {
                self::walk($node['children'] ?? [], $fn, $node['name'], $path.'/');
            }
        }
    }
}
