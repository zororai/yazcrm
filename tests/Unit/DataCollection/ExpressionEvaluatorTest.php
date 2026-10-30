<?php

namespace Tests\Unit\DataCollection;

use App\Support\DataCollection\Expression\Context;
use App\Support\DataCollection\Expression\Evaluator;
use App\Support\DataCollection\Expression\ExpressionException;
use App\Support\DataCollection\Expression\Parser;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExpressionEvaluatorTest extends TestCase
{
    private function eval(string $expr, array $data = [], mixed $current = null, array $repeatOf = [], array $repeatNames = []): mixed
    {
        $ctx = Context::forData($data, $repeatOf, $repeatNames)->withCurrent($current);

        return Evaluator::evaluate(Parser::parse($expr), $ctx);
    }

    private function truthy(string $expr, array $data = [], mixed $current = null): bool
    {
        return Evaluator::boolean($this->eval($expr, $data, $current));
    }

    public function test_field_references_and_comparisons(): void
    {
        $data = ['age' => 17, 'gender' => 'female', 'name' => 'Rudo'];

        $this->assertTrue($this->truthy('${age} < 18', $data));
        $this->assertFalse($this->truthy('${age} >= 18', $data));
        $this->assertTrue($this->truthy("\${gender} = 'female'", $data));
        $this->assertTrue($this->truthy("\${gender} != 'male'", $data));
        $this->assertTrue($this->truthy('${age} = 17', $data));
        $this->assertTrue($this->truthy("\${age} = '17'", $data)); // number vs string compares as numbers
    }

    public function test_blank_answers_behave_like_on_the_device(): void
    {
        // Blank is '' → NaN as a number, so any numeric comparison is false.
        $this->assertFalse($this->truthy('${age} > 18', []));
        $this->assertFalse($this->truthy('${age} <= 18', []));
        $this->assertTrue($this->truthy("\${age} = ''", []));
        $this->assertFalse($this->truthy('${age} = 0', []));
    }

    public function test_and_or_not_and_precedence(): void
    {
        $data = ['a' => 5, 'b' => 'yes'];

        $this->assertTrue($this->truthy("\${a} > 1 and \${b} = 'yes'", $data));
        $this->assertTrue($this->truthy("\${a} > 10 or \${b} = 'yes'", $data));
        $this->assertFalse($this->truthy("not(\${b} = 'yes')", $data));
        $this->assertTrue($this->truthy("\${a} > 10 or \${a} > 1 and \${b} = 'yes'", $data)); // and binds tighter
        $this->assertTrue($this->truthy("\${a} > 1 and (\${b} = 'no' or \${b} = 'yes')", $data));
    }

    public function test_select_functions(): void
    {
        $data = ['needs' => ['food', 'other'], 'one' => 'yes'];

        $this->assertTrue($this->truthy("selected(\${needs}, 'other')", $data));
        $this->assertFalse($this->truthy("selected(\${needs}, 'health')", $data));
        $this->assertEquals(2, $this->eval('count-selected(${needs})', $data));
        $this->assertSame('other', $this->eval('selected-at(${needs}, 1)', $data));
        $this->assertTrue($this->truthy("selected(\${one}, 'yes')", $data));
        // The XPath string form of a select_multiple works too.
        $this->assertTrue($this->truthy("selected('food other', 'other')"));
        $this->assertEquals(0, $this->eval('count-selected(${missing})', $data));
    }

    public function test_if_and_arithmetic(): void
    {
        $data = ['size' => 6, 'income' => 150.5];

        $this->assertSame('large', $this->eval("if(\${size} > 5, 'large', 'small')", $data));
        $this->assertEquals(25.0833, round($this->eval('${income} div ${size}', $data), 4));
        $this->assertEquals(0, $this->eval('${size} mod 2', $data));
        $this->assertEquals(-4, $this->eval('2 - ${size}', $data));
        $this->assertEquals(14, $this->eval('2 + 2 * ${size}', $data));
        $this->assertTrue(is_infinite($this->eval('1 div 0')));
    }

    public function test_current_value_in_constraints(): void
    {
        $this->assertTrue($this->truthy('. >= 10 and . <= 100', [], 45));
        $this->assertFalse($this->truthy('. >= 10 and . <= 100', [], 4));
        $this->assertTrue($this->truthy("regex(., '^0[0-9]{9}$')", [], '0771234567'));
        $this->assertFalse($this->truthy("regex(., '^0[0-9]{9}$')", [], '+263771234567'));
        $this->assertEquals(5, $this->eval('string-length(.)', [], 'hello'));
    }

    public function test_dates_compare_as_days(): void
    {
        CarbonImmutable::setTestNow('2026-10-30 10:00:00');

        try {
            $this->assertSame('2026-10-30', $this->eval('today()'));
            $this->assertTrue($this->truthy('. <= today()', [], '2026-10-29'));
            $this->assertFalse($this->truthy('. <= today()', [], '2026-11-02'));
            $this->assertEquals(365, $this->eval("today() - '2025-10-30'"));
            $this->assertTrue($this->truthy("\${dob} < '2010-01-01'", ['dob' => '2008-05-01']));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_string_functions(): void
    {
        $data = ['first' => 'Tendai', 'last' => 'Moyo'];

        $this->assertSame('Tendai Moyo', $this->eval("concat(\${first}, ' ', \${last})", $data));
        $this->assertTrue($this->truthy("contains(\${first}, 'end')", $data));
        $this->assertTrue($this->truthy("starts-with(\${first}, 'Ten')", $data));
        $this->assertTrue($this->truthy("ends-with(\${last}, 'yo')", $data));
        $this->assertSame('Ten', $this->eval('substr(${first}, 0, 3)', $data));
        $this->assertSame('a b', $this->eval("normalize-space('  a   b ')"));
        $this->assertSame('x', $this->eval("substring-before('x-y', '-')"));
        $this->assertSame('y', $this->eval("substring-after('x-y', '-')"));
        $this->assertSame('fallback', $this->eval("coalesce(\${missing}, 'fallback')", $data));
    }

    public function test_repeats_inside_and_outside(): void
    {
        $data = ['members' => [['member_age' => 10], ['member_age' => 30], ['member_age' => 4]]];
        $repeatOf = ['member_age' => 'members'];
        $repeatNames = ['members' => true];

        $this->assertEquals(3, $this->eval('count(${members})', $data, null, $repeatOf, $repeatNames));
        $this->assertEquals(44, $this->eval('sum(${member_age})', $data, null, $repeatOf, $repeatNames));
        $this->assertEquals(30, $this->eval('max(${member_age})', $data, null, $repeatOf, $repeatNames));

        // Inside an instance, ${member_age} is that instance's answer.
        $ctx = Context::forData($data, $repeatOf, $repeatNames)->enter($data['members'][1]);
        $this->assertTrue(Evaluator::truthy(Parser::parse('${member_age} >= 18'), $ctx));
    }

    public function test_choice_filter_columns(): void
    {
        $ast = Parser::parse('county = ${county}', true);
        $ctx = Context::forData(['county' => 'harare'])->withChoice(['name' => 'mbare', 'county' => 'harare']);
        $this->assertTrue(Evaluator::truthy($ast, $ctx));
        $this->assertFalse(Evaluator::truthy($ast, $ctx->withChoice(['name' => 'nkulumane', 'county' => 'bulawayo'])));
    }

    public function test_references_are_listed(): void
    {
        $refs = Parser::references(Parser::parse("selected(\${a}, 'x') and \${b} > count(\${c}) or \${a} = 1"));
        sort($refs);
        $this->assertSame(['a', 'b', 'c'], $refs);
    }

    #[DataProvider('invalidExpressions')]
    public function test_invalid_expressions_are_rejected(string $expr): void
    {
        $this->expectException(ExpressionException::class);
        Parser::parse($expr);
    }

    public static function invalidExpressions(): array
    {
        return [
            'unclosed paren'     => ['(${a} > 1'],
            'unclosed string'    => ["\${a} = 'yes"],
            'unknown function'   => ['frobnicate(${a})'],
            'wrong arity'        => ['selected(${a})'],
            'bare name'          => ['age > 18'],
            'xpath path'         => ['/data/age > 18'],
            'dangling operator'  => ['${a} >'],
            'bad reference'      => ['${} = 1'],
            'empty'              => ['   '],
            'trailing tokens'    => ['${a} ${b}'],
        ];
    }
}
