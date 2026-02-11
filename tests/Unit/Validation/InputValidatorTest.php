<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Validation;

use MonkeysLegion\GraphQL\Error\ValidationError;
use MonkeysLegion\GraphQL\Validation\InputValidator;
use MonkeysLegion\GraphQL\Validation\RuleSet;
use PHPUnit\Framework\TestCase;

final class InputValidatorTest extends TestCase
{
    private InputValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InputValidator();
    }

    public function testRequiredFieldPasses(): void
    {
        $rules = RuleSet::fromArray(['name' => 'required']);
        $this->validator->validate(['name' => 'Alice'], $rules);
        $this->addToAssertionCount(1); // No exception
    }

    public function testRequiredFieldFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['name' => 'required']);
        $this->validator->validate(['name' => ''], $rules);
    }

    public function testStringRulePasses(): void
    {
        $rules = RuleSet::fromArray(['name' => 'string']);
        $this->validator->validate(['name' => 'Alice'], $rules);
        $this->addToAssertionCount(1);
    }

    public function testStringRuleFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['name' => 'string']);
        $this->validator->validate(['name' => 123], $rules);
    }

    public function testEmailRulePasses(): void
    {
        $rules = RuleSet::fromArray(['email' => 'email']);
        $this->validator->validate(['email' => 'test@example.com'], $rules);
        $this->addToAssertionCount(1);
    }

    public function testEmailRuleFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['email' => 'email']);
        $this->validator->validate(['email' => 'bad-email'], $rules);
    }

    public function testMinLengthPasses(): void
    {
        $rules = RuleSet::fromArray(['name' => 'min_length:3']);
        $this->validator->validate(['name' => 'Alice'], $rules);
        $this->addToAssertionCount(1);
    }

    public function testMinLengthFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['name' => 'min_length:3']);
        $this->validator->validate(['name' => 'AB'], $rules);
    }

    public function testMaxLengthPasses(): void
    {
        $rules = RuleSet::fromArray(['code' => 'max_length:5']);
        $this->validator->validate(['code' => 'ABC'], $rules);
        $this->addToAssertionCount(1);
    }

    public function testMaxLengthFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['code' => 'max_length:3']);
        $this->validator->validate(['code' => 'ABCDEF'], $rules);
    }

    public function testInRulePasses(): void
    {
        $rules = RuleSet::fromArray(['status' => 'in:active,inactive']);
        $this->validator->validate(['status' => 'active'], $rules);
        $this->addToAssertionCount(1);
    }

    public function testInRuleFails(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['status' => 'in:active,inactive']);
        $this->validator->validate(['status' => 'deleted'], $rules);
    }

    public function testMultipleRulesPipeSeparated(): void
    {
        $this->expectException(ValidationError::class);
        $rules = RuleSet::fromArray(['name' => 'required|min_length:5']);
        $this->validator->validate(['name' => 'AB'], $rules);
    }

    public function testNullValuesSkipNonRequiredRules(): void
    {
        $rules = RuleSet::fromArray(['email' => 'email']);
        $this->validator->validate(['email' => null], $rules);
        $this->addToAssertionCount(1);
    }

    public function testRuleSetFromArrayPipe(): void
    {
        $rs = RuleSet::fromArray(['name' => 'required|string|min_length:2']);
        $rules = $rs->forField('name');
        self::assertCount(3, $rules);
        self::assertSame('required', $rules[0]);
        self::assertSame('string', $rules[1]);
        self::assertSame('min_length:2', $rules[2]);
    }

    public function testRuleSetAdd(): void
    {
        $rs = new RuleSet();
        self::assertTrue($rs->isEmpty());
        $rs->add('name', 'required');
        $rs->add('name', 'string');
        self::assertFalse($rs->isEmpty());
        self::assertCount(2, $rs->forField('name'));
    }
}
