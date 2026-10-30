<?php

namespace App\Support\DataCollection\Expression;

// Several values at once: a repeat's instances (${repeat_name}), or one
// field's answers across all instances when referenced from outside its
// repeat (${child} in sum(), count(), join()...).
final class Nodeset
{
    public function __construct(public readonly array $items)
    {
    }

    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }
}
