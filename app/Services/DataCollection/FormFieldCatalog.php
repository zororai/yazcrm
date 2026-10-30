<?php

namespace App\Services\DataCollection;

use App\Models\DataCollectionForm;
use App\Support\DataCollection\SchemaWalker;

// Every field a form has had across its deployed and archived versions,
// since old versions keep receiving data. The newest version's definition
// wins; a field flagged PII in any version stays PII.
class FormFieldCatalog
{
    public function __construct(
        public readonly array $fields,   // path => [name, path, type, label, repeat, pii, choices{name=>label}, node]
        public readonly array $repeats,  // name => [name, label, parent]
        public readonly array $pii,      // paths
    ) {
    }

    public static function for(DataCollectionForm $form): self
    {
        $versions = $form->versions()
            ->whereIn('status', ['deployed', 'archived'])
            ->get(['id', 'schema', 'version_number']); // newest first

        // Not deployed yet: describe the latest draft so screens aren't empty.
        if ($versions->isEmpty()) {
            $versions = $form->versions()->limit(1)->get(['id', 'schema', 'version_number']);
        }

        $fields = [];
        $repeats = [];
        $pii = [];
        $order = [];

        foreach ($versions as $i => $version) {
            $schema = $version->schema ?? [];
            foreach (SchemaWalker::fields($schema) as $position => $field) {
                if ($field['pii']) {
                    $pii[$field['path']] = true;
                }
                if (isset($fields[$field['path']])) {
                    continue;
                }
                $fields[$field['path']] = [
                    'name'    => $field['name'],
                    'path'    => $field['path'],
                    'type'    => $field['type'],
                    'label'   => $field['label'],
                    'repeat'  => $field['repeat'],
                    'choices' => in_array($field['type'], ['select_one', 'select_multiple'], true)
                        ? SchemaWalker::choiceLabels($schema, $field['node']) : [],
                    'node'    => $field['node'],
                ];
                $order[$field['path']] = [$i, $position];
            }
            $repeats += SchemaWalker::repeats($schema);
        }

        // Newest version's order first, then fields only older versions had.
        uksort($fields, fn ($a, $b) => $order[$a] <=> $order[$b]);
        foreach ($fields as $path => &$field) {
            $field['pii'] = isset($pii[$path]);
        }

        return new self($fields, $repeats, array_keys($pii));
    }

    public function topLevel(): array
    {
        return array_filter($this->fields, fn ($f) => $f['repeat'] === null);
    }

    public function inRepeat(string $repeat): array
    {
        return array_filter($this->fields, fn ($f) => $f['repeat'] === $repeat);
    }

    // PII paths this viewer may not see.
    public function hiddenFor(bool $canSeePii): array
    {
        return $canSeePii ? [] : $this->pii;
    }

    // Replaces hidden answers (at any depth, inside repeats too) with a mask.
    public function mask(array $data, array $hidden, string $prefix = ''): array
    {
        if (! $hidden) {
            return $data;
        }
        foreach ($data as $key => $value) {
            $path = $prefix.$key;
            if (in_array($path, $hidden, true)) {
                $data[$key] = '•••';
            } elseif (isset($this->repeats[$key]) && is_array($value)) {
                foreach ($value as $i => $instance) {
                    if (is_array($instance)) {
                        $data[$key][$i] = $this->mask($instance, $hidden, $path.'/');
                    }
                }
            }
        }

        return $data;
    }
}
