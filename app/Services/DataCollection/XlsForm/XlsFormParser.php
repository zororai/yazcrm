<?php

namespace App\Services\DataCollection\XlsForm;

use App\Support\DataCollection\Expression\ExpressionException;
use App\Support\DataCollection\Expression\Parser;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

// Turns an XLSForm workbook (survey, choices, settings sheets) into the
// canonical JSON schema the app downloads (see SchemaWalker), and lists every
// problem as {sheet, row, column, message}. A version with errors is stored
// but can't be deployed.
class XlsFormParser
{
    public const QUESTION_TYPES = [
        'text', 'integer', 'decimal', 'select_one', 'select_multiple', 'date', 'time',
        'datetime', 'geopoint', 'photo', 'note', 'calculate',
    ];

    // XLSForm metadata rows the app records by itself; accepted and dropped.
    private const METADATA_TYPES = [
        'start', 'end', 'today', 'deviceid', 'phonenumber', 'username', 'email',
        'audit', 'simserial', 'subscriberid', 'start-geopoint',
    ];

    private const ALIASES = ['string' => 'text', 'int' => 'integer', 'image' => 'photo'];

    // Columns that may be given per language (label::English (en)).
    private const TRANSLATABLE = ['label', 'hint', 'constraint_message', 'required_message'];

    private const COLUMN_ALIASES = [
        'list name'          => 'list_name',
        'constraint message' => 'constraint_message',
        'jr:constraintmsg'   => 'constraint_message',
        'required message'   => 'required_message',
        'jr:requiredmsg'     => 'required_message',
        'choice filter'      => 'choice_filter',
        'readonly'           => 'read_only',
        'read only'          => 'read_only',
        'jr:count'           => 'repeat_count',
        'repeat count'       => 'repeat_count',
        'calculate'          => 'calculation',
    ];

    private array $errors = [];

    public function parseFile(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable) {
            return ['schema' => null, 'errors' => [self::error('file', null, null, 'This is not a readable .xlsx workbook.')]];
        }

        return $this->parse($book);
    }

    public function parse(Spreadsheet $book): array
    {
        $this->errors = [];

        $sheets = [];
        foreach ($book->getAllSheets() as $sheet) {
            $sheets[strtolower(trim($sheet->getTitle()))] = $sheet;
        }

        if (! isset($sheets['survey'])) {
            return ['schema' => null, 'errors' => [self::error('survey', null, null, 'The workbook has no "survey" sheet.')]];
        }

        $settings = isset($sheets['settings']) ? $this->readSettings($sheets['settings']) : [];
        $choices = isset($sheets['choices']) ? $this->readChoices($sheets['choices']) : [];
        [$children, $expressions, $names] = $this->readSurvey($sheets['survey'], $choices);

        $this->checkExpressions($expressions, $names);

        $schema = [
            'title'            => $settings['form_title'] ?? null,
            'id_string'        => $settings['form_id'] ?? null,
            'default_language' => $settings['default_language'] ?? null,
            'settings'         => $settings,
            'children'         => $children,
            'choices'          => (object) array_map(fn ($list) => $list['items'], $choices),
        ];

        return ['schema' => json_decode(json_encode($schema), true), 'errors' => $this->errors];
    }

    // ── Sheets ──────────────────────────────────────────────────────────────

    // [rowNumber => [column => value]] plus the header map [column => letter].
    private function rows(Worksheet $sheet): array
    {
        $raw = $sheet->toArray(null, false, false, false);
        if (! $raw) {
            return [[], []];
        }

        $headers = [];
        foreach (array_shift($raw) as $i => $h) {
            $h = trim((string) $h);
            if ($h === '') {
                continue;
            }
            [$base, $lang] = array_pad(explode('::', $h, 2), 2, null);
            $base = strtolower(trim($base));
            $base = self::COLUMN_ALIASES[$base] ?? str_replace(' ', '_', $base);
            $headers[$i] = $lang !== null ? $base.'::'.trim($lang) : $base;
        }

        $rows = [];
        foreach ($raw as $offset => $cells) {
            $row = [];
            foreach ($headers as $i => $key) {
                $value = self::cell($cells[$i] ?? null);
                if ($value !== '') {
                    $row[$key] = $value;
                }
            }
            if ($row) {
                $rows[$offset + 2] = $row; // Excel row number (header is row 1)
            }
        }

        return [$rows, array_flip($headers)];
    }

    private static function cell(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_float($v) && floor($v) == $v) {
            return (string) (int) $v;
        }

        return trim((string) $v);
    }

    private function readSettings(Worksheet $sheet): array
    {
        [$rows] = $this->rows($sheet);
        $first = reset($rows) ?: [];

        return array_filter([
            'form_title'       => $first['form_title'] ?? null,
            'form_id'          => $first['form_id'] ?? null,
            'version'          => $first['version'] ?? null,
            'default_language' => $first['default_language'] ?? null,
            'instance_name'    => $first['instance_name'] ?? null,
        ], fn ($v) => $v !== null);
    }

    private function readChoices(Worksheet $sheet): array
    {
        [$rows] = $this->rows($sheet);
        $lists = [];

        foreach ($rows as $n => $row) {
            $list = $row['list_name'] ?? null;
            if ($list === null) {
                $this->errors[] = self::error('choices', $n, 'list_name', 'Choice has no list_name.');
                continue;
            }
            $name = $row['name'] ?? null;
            if ($name === null) {
                $this->errors[] = self::error('choices', $n, 'name', "Choice in list '{$list}' has no name.");
                continue;
            }

            $lists[$list] ??= ['row' => $n, 'items' => [], 'seen' => []];
            if (isset($lists[$list]['seen'][$name])) {
                $this->errors[] = self::error('choices', $n, 'name', "Duplicate choice '{$name}' in list '{$list}'.");
                continue;
            }
            $lists[$list]['seen'][$name] = true;

            $label = $this->translated($row, 'label');
            if ($label === null) {
                $this->errors[] = self::error('choices', $n, 'label', "Choice '{$name}' in list '{$list}' has no label.");
            }

            // Extra columns stay on the choice for choice_filter (e.g. county).
            $extra = array_diff_key($row, ['list_name' => 1, 'name' => 1, 'label' => 1]);
            $extra = array_filter($extra, fn ($k) => ! str_starts_with($k, 'label::') && ! str_starts_with($k, 'media::'), ARRAY_FILTER_USE_KEY);

            $lists[$list]['items'][] = ['name' => $name, 'label' => $label ?? $name] + $extra;
        }

        return $lists;
    }

    // Returns [children, expressions to check, known names].
    private function readSurvey(Worksheet $sheet, array $choices): array
    {
        [$rows] = $this->rows($sheet);

        $root = [];
        $stack = []; // open groups/repeats: ['node' => ..., 'kind' => ..., 'row' => n]
        $names = [];
        $expressions = [];

        foreach ($rows as $n => $row) {
            $rawType = preg_replace('/\s+/', ' ', strtolower($row['type'] ?? ''));
            if ($rawType === '') {
                $this->errors[] = self::error('survey', $n, 'type', 'Row has no type.');
                continue;
            }

            [$type, $listName, $typeError] = $this->type($rawType, $row['type']);
            if ($typeError) {
                $this->errors[] = self::error('survey', $n, 'type', $typeError);
                continue;
            }

            if ($type === 'end_group' || $type === 'end_repeat') {
                $kind = $type === 'end_group' ? 'group' : 'repeat';
                $open = array_pop($stack);
                if (! $open) {
                    $this->errors[] = self::error('survey', $n, 'type', "\"end {$kind}\" has no matching \"begin {$kind}\".");
                    continue;
                }
                if ($open['kind'] !== $kind) {
                    $this->errors[] = self::error('survey', $n, 'type', "\"end {$kind}\" closes the {$open['kind']} '{$open['node']['name']}' opened on row {$open['row']}.");
                }
                if (! $open['node']['children']) {
                    $this->errors[] = self::error('survey', $open['row'], 'type', "The {$open['kind']} '{$open['node']['name']}' is empty.");
                }
                $this->append($root, $stack, $open['node']);
                continue;
            }

            if (in_array($type, self::METADATA_TYPES, true)) {
                continue;
            }

            $name = $row['name'] ?? null;
            if ($name === null) {
                $this->errors[] = self::error('survey', $n, 'name', 'Row has no name.');
                continue;
            }
            if (! preg_match('/^[A-Za-z_][A-Za-z0-9_.\-]*$/', $name)) {
                $this->errors[] = self::error('survey', $n, 'name', "'{$name}' is not a valid name: start with a letter or _, then use letters, digits, _, - or . only.");
            }
            if (isset($names[$name])) {
                $this->errors[] = self::error('survey', $n, 'name', "Duplicate name '{$name}' (first used on row {$names[$name]}).");
            } else {
                $names[$name] = $n;
            }

            $node = ['type' => $type, 'name' => $name];
            foreach (['label', 'hint', 'constraint_message', 'required_message'] as $col) {
                $value = $this->translated($row, $col);
                if ($value !== null) {
                    $node[$col] = $value;
                }
            }

            $askable = ! in_array($type, ['group', 'repeat', 'calculate'], true);
            if ($askable && ! isset($node['label'])) {
                $this->errors[] = self::error('survey', $n, 'label', "'{$name}' has no label.");
            }

            $required = $row['required'] ?? null;
            if ($required !== null) {
                $flag = strtolower($required);
                if (in_array($flag, ['yes', 'true', 'true()', '1'], true)) {
                    $node['required'] = 'yes';
                } elseif (! in_array($flag, ['no', 'false', 'false()', '0'], true)) {
                    $node['required'] = $required;
                    $expressions[] = ['survey', $n, 'required', $required, false];
                }
            }

            foreach (['relevant', 'constraint', 'calculation', 'repeat_count'] as $col) {
                if (isset($row[$col])) {
                    $node[$col] = $row[$col];
                    $expressions[] = ['survey', $n, $col, $row[$col], false];
                }
            }
            if (isset($row['choice_filter'])) {
                $node['choice_filter'] = $row['choice_filter'];
                $expressions[] = ['survey', $n, 'choice_filter', $row['choice_filter'], true];
            }
            foreach (['default', 'appearance'] as $col) {
                if (isset($row[$col])) {
                    $node[$col] = $row[$col];
                }
            }
            if (self::truthy($row['read_only'] ?? null)) {
                $node['read_only'] = true;
            }
            if (self::truthy($row['pii'] ?? null)) {
                $node['pii'] = true;
            }

            if ($type === 'calculate' && ! isset($node['calculation'])) {
                $this->errors[] = self::error('survey', $n, 'calculation', "Calculate '{$name}' has no calculation.");
            }

            if ($listName !== null) {
                $node['list_name'] = $listName;
                if (! isset($choices[$listName])) {
                    $this->errors[] = self::error('survey', $n, 'type', "'{$name}' uses the choice list '{$listName}', which is not on the choices sheet.");
                } else {
                    $node['choices'] = $choices[$listName]['items'];
                }
            }

            if ($type === 'group' || $type === 'repeat') {
                $node['children'] = [];
                $stack[] = ['node' => $node, 'kind' => $type, 'row' => $n];
                continue;
            }

            $this->append($root, $stack, $node);
        }

        // Anything still open was never closed.
        while ($open = array_pop($stack)) {
            $this->errors[] = self::error('survey', $open['row'], 'type', "\"begin {$open['kind']}\" '{$open['node']['name']}' is never closed with \"end {$open['kind']}\".");
            $this->append($root, $stack, $open['node']);
        }

        return [$root, $expressions, $names];
    }

    // Returns [type, list name or null, error or null].
    private function type(string $lower, string $original): array
    {
        $lower = preg_replace('/^(begin|end) (group|repeat)$/', '$1_$2', $lower);
        if (in_array($lower, ['begin_group', 'begin_repeat'], true)) {
            return [substr($lower, 6), null, null];
        }
        if (in_array($lower, ['end_group', 'end_repeat'], true)) {
            return [$lower, null, null];
        }

        $parts = explode(' ', $lower);
        $originalParts = preg_split('/\s+/', trim($original));
        $head = $parts[0];

        if (in_array($head, ['select', 'select_one', 'select_multiple'], true)) {
            if ($head === 'select') { // "select one x" / "select multiple x"
                $head = 'select_'.($parts[1] ?? '');
                array_splice($parts, 1, 1);
                array_splice($originalParts, 1, 1);
            }
            if (! in_array($head, ['select_one', 'select_multiple'], true)) {
                return [null, null, "Unknown question type '{$original}'."];
            }
            if (count($parts) > 2) {
                return [null, null, "'{$original}': or_other and other select options are not supported; add an 'other' choice and a text question instead."];
            }
            $list = $originalParts[1] ?? null;
            if ($list === null) {
                return [null, null, "'{$original}' has no choice list: write it as \"{$head} list_name\"."];
            }

            return [$head, $list, null];
        }

        if (count($parts) > 1) {
            return [null, null, "Unknown question type '{$original}'."];
        }

        $type = self::ALIASES[$head] ?? $head;
        if (in_array($type, self::QUESTION_TYPES, true) || in_array($type, self::METADATA_TYPES, true)) {
            return [$type, null, null];
        }
        if (in_array($type, ['file', 'audio', 'video', 'barcode', 'range', 'acknowledge', 'geotrace', 'geoshape', 'hidden', 'rank'], true)) {
            return [null, null, "The type '{$original}' is not supported by the YAZ app yet."];
        }

        return [null, null, "Unknown question type '{$original}'."];
    }

    private function append(array &$root, array &$stack, array $node): void
    {
        if ($stack) {
            $stack[array_key_last($stack)]['node']['children'][] = $node;
        } else {
            $root[] = $node;
        }
    }

    // Plain column, or per-language columns as {lang: text} (plain → "default").
    private function translated(array $row, string $column): string|array|null
    {
        $byLang = [];
        foreach ($row as $key => $value) {
            if (str_starts_with($key, $column.'::')) {
                $byLang[substr($key, strlen($column) + 2)] = $value;
            }
        }
        if (! $byLang) {
            return $row[$column] ?? null;
        }
        if (isset($row[$column])) {
            $byLang = ['default' => $row[$column]] + $byLang;
        }

        return $byLang;
    }

    private function checkExpressions(array $expressions, array $names): void
    {
        foreach ($expressions as [$sheet, $row, $column, $expression, $allowColumns]) {
            try {
                $ast = Parser::parse($expression, $allowColumns);
            } catch (ExpressionException $e) {
                $this->errors[] = self::error($sheet, $row, $column, "Invalid {$column} expression: {$e->getMessage()}");
                continue;
            }
            foreach (Parser::references($ast) as $ref) {
                if (! isset($names[$ref])) {
                    $this->errors[] = self::error($sheet, $row, $column, "\${{$ref}} is not a field in this form.");
                }
            }
        }
    }

    private static function truthy(?string $v): bool
    {
        return $v !== null && in_array(strtolower($v), ['yes', 'true', 'true()', '1'], true);
    }

    private static function error(string $sheet, ?int $row, ?string $column, string $message): array
    {
        return compact('sheet', 'row', 'column', 'message');
    }
}
