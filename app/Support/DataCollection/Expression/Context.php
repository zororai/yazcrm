<?php

namespace App\Support\DataCollection\Expression;

// What ${name}, '.' and choice columns resolve to during one evaluation.
//
// $scopes is the chain of answer maps from the innermost repeat instance out
// to the root data, so ${sibling} inside a repeat finds the same instance's
// answer first. $repeatOf maps every field inside a repeat to that repeat's
// name, which lets ${child} from outside the repeat (e.g. sum(${age}))
// collect the answers of all instances.
class Context
{
    public function __construct(
        private array $scopes,
        private readonly array $repeatOf = [],
        private readonly array $repeatNames = [],
        public readonly mixed $current = null,
        public readonly array $choice = [],
    ) {
    }

    public static function forData(array $data, array $repeatOf = [], array $repeatNames = []): self
    {
        return new self([$data], $repeatOf, $repeatNames);
    }

    public function withCurrent(mixed $value): self
    {
        return new self($this->scopes, $this->repeatOf, $this->repeatNames, $value, $this->choice);
    }

    public function withChoice(array $choice): self
    {
        return new self($this->scopes, $this->repeatOf, $this->repeatNames, $this->current, $choice);
    }

    public function enter(array $instance): self
    {
        return new self([$instance, ...$this->scopes], $this->repeatOf, $this->repeatNames, null, $this->choice);
    }

    public function resolve(string $name): mixed
    {
        // A repeat itself: its instances, for count(${members}).
        if (isset($this->repeatNames[$name])) {
            foreach ($this->scopes as $scope) {
                if (array_key_exists($name, $scope)) {
                    return new Nodeset(is_array($scope[$name]) ? array_values($scope[$name]) : []);
                }
            }

            return new Nodeset([]);
        }

        foreach ($this->scopes as $scope) {
            if (array_key_exists($name, $scope)) {
                return $scope[$name];
            }
        }

        // A field inside a repeat we're not in: every instance's answer.
        if (isset($this->repeatOf[$name])) {
            return new Nodeset($this->collect($name, $this->repeatOf[$name]));
        }

        return null;
    }

    private function collect(string $name, string $repeat): array
    {
        $root = end($this->scopes) ?: [];
        $instances = $this->instancesOf($repeat, $root);

        $values = [];
        foreach ($instances as $instance) {
            if (is_array($instance) && array_key_exists($name, $instance)) {
                $values[] = $instance[$name];
            }
        }

        return $values;
    }

    // Instances of a (possibly nested) repeat, found from the root down.
    private function instancesOf(string $repeat, array $root): array
    {
        if (array_key_exists($repeat, $root) && is_array($root[$repeat])) {
            return array_values($root[$repeat]);
        }

        $parent = $this->repeatOf[$repeat] ?? null;
        if (! $parent) {
            return [];
        }

        $instances = [];
        foreach ($this->instancesOf($parent, $root) as $outer) {
            if (is_array($outer) && is_array($outer[$repeat] ?? null)) {
                array_push($instances, ...array_values($outer[$repeat]));
            }
        }

        return $instances;
    }
}
