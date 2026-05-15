<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Validation;

use MonkeysLegion\GraphQL\Validation\RuleSet;
use PHPUnit\Framework\TestCase;

final class RuleSetTest extends TestCase
{
    public function testFromArrayWithPipeDelimited(): void
    {
        $set = RuleSet::fromArray([
            'email' => 'required|email|max:255',
            'name'  => 'required|string',
        ]);

        $this->assertSame(['required', 'email', 'max:255'], $set->forField('email'));
        $this->assertSame(['required', 'string'], $set->forField('name'));
    }

    public function testFromArrayWithArray(): void
    {
        $set = RuleSet::fromArray([
            'email' => ['required', 'email'],
        ]);

        $this->assertSame(['required', 'email'], $set->forField('email'));
    }

    public function testAddRule(): void
    {
        $set = new RuleSet();
        $set->add('name', 'required');
        $set->add('name', 'string');

        $this->assertSame(['required', 'string'], $set->forField('name'));
    }

    public function testSetRules(): void
    {
        $set = new RuleSet();
        $set->set('name', ['required', 'string']);
        $this->assertSame(['required', 'string'], $set->forField('name'));

        $set->set('name', ['nullable']);
        $this->assertSame(['nullable'], $set->forField('name'));
    }

    public function testIsEmpty(): void
    {
        $set = new RuleSet();
        $this->assertTrue($set->isEmpty());

        $set->add('field', 'required');
        $this->assertFalse($set->isEmpty());
    }

    public function testForFieldReturnsEmptyForMissing(): void
    {
        $set = new RuleSet();
        $this->assertSame([], $set->forField('missing'));
    }

    public function testRulesReturnsAll(): void
    {
        $set = RuleSet::fromArray([
            'a' => 'required',
            'b' => ['email'],
        ]);

        $rules = $set->rules();
        $this->assertArrayHasKey('a', $rules);
        $this->assertArrayHasKey('b', $rules);
    }
}
