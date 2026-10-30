<?php

namespace App\Support\DataCollection\Expression;

// Parses the XLSForm expression subset (relevant, constraint, calculation,
// required, choice_filter) into a small AST of arrays:
//   ['num', float] ['str', string] ['ref', name] ['self'] ['col', name]
//   ['call', name, [args]] ['bin', op, left, right] ['neg', expr]
// Precedence follows XPath 1.0: or < and < = != < relational < + - < * div mod < unary -.
class Parser
{
    // name => [min args, max args or null for unlimited]
    public const FUNCTIONS = [
        'if'               => [3, 3],
        'selected'         => [2, 2],
        'count-selected'   => [1, 1],
        'selected-at'      => [2, 2],
        'not'              => [1, 1],
        'true'             => [0, 0],
        'false'            => [0, 0],
        'boolean'          => [1, 1],
        'number'           => [1, 1],
        'string'           => [1, 1],
        'int'              => [1, 1],
        'round'            => [1, 2],
        'floor'            => [1, 1],
        'ceiling'          => [1, 1],
        'abs'              => [1, 1],
        'pow'              => [2, 2],
        'sqrt'             => [1, 1],
        'min'              => [1, null],
        'max'              => [1, null],
        'sum'              => [1, 1],
        'count'            => [1, 1],
        'today'            => [0, 0],
        'now'              => [0, 0],
        'date'             => [1, 1],
        'decimal-date-time' => [1, 1],
        'concat'           => [0, null],
        'join'             => [2, null],
        'contains'         => [2, 2],
        'starts-with'      => [2, 2],
        'ends-with'        => [2, 2],
        'substr'           => [2, 3],
        'substring-before' => [2, 2],
        'substring-after'  => [2, 2],
        'string-length'    => [0, 1],
        'normalize-space'  => [0, 1],
        'regex'            => [2, 2],
        'coalesce'         => [2, 2],
        'once'             => [1, 1],
    ];

    private array $tokens = [];
    private int $pos = 0;

    private static array $cache = [];

    // $allowColumns: choice_filter expressions refer to choice-sheet columns
    // by bare name (e.g. county=${county}).
    public static function parse(string $expression, bool $allowColumns = false): array
    {
        $key = ($allowColumns ? 'c:' : 'e:').$expression;
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $parser = new self();
        $parser->tokens = self::tokenize($expression, $allowColumns);
        $parser->pos = 0;

        if (! $parser->tokens) {
            throw new ExpressionException('The expression is empty.');
        }

        $ast = $parser->parseOr();
        if ($parser->pos < count($parser->tokens)) {
            throw new ExpressionException("Unexpected '{$parser->tokens[$parser->pos][1]}'.");
        }

        if (count(self::$cache) > 2000) {
            self::$cache = [];
        }

        return self::$cache[$key] = $ast;
    }

    // Every ${name} the expression refers to.
    public static function references(array $ast): array
    {
        $refs = [];
        $walk = function (array $node) use (&$walk, &$refs) {
            match ($node[0]) {
                'ref'  => $refs[$node[1]] = true,
                'call' => array_map($walk, $node[2]),
                'bin'  => [$walk($node[2]), $walk($node[3])],
                'neg'  => $walk($node[1]),
                default => null,
            };
        };
        $walk($ast);

        return array_keys($refs);
    }

    // Token: [type, text]. Types: num str ref self name op ( ) ,
    private static function tokenize(string $s, bool $allowColumns): array
    {
        $tokens = [];
        $i = 0;
        $n = strlen($s);

        while ($i < $n) {
            $c = $s[$i];

            if (ctype_space($c)) {
                $i++;
                continue;
            }

            if ($c === '$') {
                if (! preg_match('/\G\$\{\s*([A-Za-z_][A-Za-z0-9_.\-]*)\s*\}/', $s, $m, 0, $i)) {
                    throw new ExpressionException('A field reference must look like ${field_name}.');
                }
                $tokens[] = ['ref', $m[1]];
                $i += strlen($m[0]);
                continue;
            }

            if ($c === "'" || $c === '"') {
                $end = strpos($s, $c, $i + 1);
                if ($end === false) {
                    throw new ExpressionException('A quoted string is not closed.');
                }
                $tokens[] = ['str', substr($s, $i + 1, $end - $i - 1)];
                $i = $end + 1;
                continue;
            }

            if (preg_match('/\G(\d+(\.\d*)?|\.\d+)/', $s, $m, 0, $i)) {
                $tokens[] = ['num', $m[0]];
                $i += strlen($m[0]);
                continue;
            }

            if ($c === '.') {
                if (($s[$i + 1] ?? '') === '.') {
                    throw new ExpressionException("Relative paths ('..') are not supported; use \${field_name}.");
                }
                $tokens[] = ['self', '.'];
                $i++;
                continue;
            }

            if (preg_match('/\G(!=|<=|>=|=|<|>|\+|-|\*)/', $s, $m, 0, $i)) {
                $tokens[] = ['op', $m[0]];
                $i += strlen($m[0]);
                continue;
            }

            if ($c === '(' || $c === ')' || $c === ',') {
                $tokens[] = [$c, $c];
                $i++;
                continue;
            }

            if (preg_match('/\G[A-Za-z_][A-Za-z0-9_\-:]*/', $s, $m, 0, $i)) {
                $word = $m[0];
                $i += strlen($word);

                // Keywords first: "${a} and (${b})" is an operator, not a call.
                $next = ltrim(substr($s, $i));
                if (in_array($word, ['and', 'or', 'div', 'mod'], true)) {
                    $tokens[] = ['op', $word];
                } elseif (str_starts_with($next, '(')) {
                    $tokens[] = ['name', $word];
                } elseif ($allowColumns) {
                    $tokens[] = ['col', $word];
                } else {
                    throw new ExpressionException("Unknown name '{$word}': refer to fields as \${{$word}}.");
                }
                continue;
            }

            if ($c === '/') {
                throw new ExpressionException("Paths like '/data/...' are not supported; use \${field_name}.");
            }

            throw new ExpressionException("Unexpected character '{$c}'.");
        }

        return $tokens;
    }

    private function peekOp(string ...$ops): ?string
    {
        $t = $this->tokens[$this->pos] ?? null;

        return $t && $t[0] === 'op' && in_array($t[1], $ops, true) ? $t[1] : null;
    }

    private function binary(callable $next, array $ops): array
    {
        $left = $next();
        while ($op = $this->peekOp(...$ops)) {
            $this->pos++;
            $left = ['bin', $op, $left, $next()];
        }

        return $left;
    }

    private function parseOr(): array
    {
        return $this->binary(fn () => $this->parseAnd(), ['or']);
    }

    private function parseAnd(): array
    {
        return $this->binary(fn () => $this->parseEquality(), ['and']);
    }

    private function parseEquality(): array
    {
        return $this->binary(fn () => $this->parseRelational(), ['=', '!=']);
    }

    private function parseRelational(): array
    {
        return $this->binary(fn () => $this->parseAdditive(), ['<', '<=', '>', '>=']);
    }

    private function parseAdditive(): array
    {
        return $this->binary(fn () => $this->parseMultiplicative(), ['+', '-']);
    }

    private function parseMultiplicative(): array
    {
        return $this->binary(fn () => $this->parseUnary(), ['*', 'div', 'mod']);
    }

    private function parseUnary(): array
    {
        if ($this->peekOp('-')) {
            $this->pos++;

            return ['neg', $this->parseUnary()];
        }

        return $this->parsePrimary();
    }

    private function parsePrimary(): array
    {
        $t = $this->tokens[$this->pos] ?? null;
        if (! $t) {
            throw new ExpressionException('The expression ends too early.');
        }
        $this->pos++;

        switch ($t[0]) {
            case 'num':
                return ['num', (float) $t[1]];
            case 'str':
                return ['str', $t[1]];
            case 'ref':
                return ['ref', $t[1]];
            case 'self':
                return ['self'];
            case 'col':
                return ['col', $t[1]];
            case '(':
                $inner = $this->parseOr();
                $this->expect(')');

                return $inner;
            case 'name':
                return $this->parseCall($t[1]);
        }

        throw new ExpressionException("Unexpected '{$t[1]}'.");
    }

    private function parseCall(string $name): array
    {
        if (! isset(self::FUNCTIONS[$name])) {
            throw new ExpressionException("Unknown function '{$name}()'.");
        }

        $this->expect('(');
        $args = [];
        if (($this->tokens[$this->pos][0] ?? null) !== ')') {
            do {
                $args[] = $this->parseOr();
            } while ($this->accept(','));
        }
        $this->expect(')');

        [$min, $max] = self::FUNCTIONS[$name];
        $count = count($args);
        if ($count < $min || ($max !== null && $count > $max)) {
            $expected = $max === null ? "at least {$min}" : ($min === $max ? (string) $min : "{$min}–{$max}");
            throw new ExpressionException("{$name}() takes {$expected} argument(s), {$count} given.");
        }

        return ['call', $name, $args];
    }

    private function expect(string $type): void
    {
        if (! $this->accept($type)) {
            $got = $this->tokens[$this->pos][1] ?? 'the end';
            throw new ExpressionException("Expected '{$type}' but found '{$got}'.");
        }
    }

    private function accept(string $type): bool
    {
        if (($this->tokens[$this->pos][0] ?? null) === $type) {
            $this->pos++;

            return true;
        }

        return false;
    }
}
