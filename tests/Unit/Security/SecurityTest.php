<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Tests\Unit\Security;

use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use MonkeysLegion\GraphQL\Security\DepthLimiter;
use MonkeysLegion\GraphQL\Security\ComplexityAnalyzer;
use MonkeysLegion\GraphQL\Security\IntrospectionControl;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase
{
    private Schema $schema;

    protected function setUp(): void
    {
        $userType = new ObjectType([
            'name'   => 'User',
            'fields' => static function () use (&$userType) {
                return [
                    'name'    => ['type' => Type::string()],
                    'friends' => ['type' => Type::listOf($userType)],
                ];
            },
        ]);

        $this->schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'Query',
                'fields' => [
                    'user' => ['type' => $userType, 'resolve' => static fn() => null],
                ],
            ]),
        ]);
    }

    public function testDepthLimiterAllowsShallowQuery(): void
    {
        $query = '{ user { name } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new DepthLimiter(5)]);
        self::assertEmpty($errors);
    }

    public function testDepthLimiterBlocksDeepQuery(): void
    {
        $query = '{ user { friends { friends { friends { friends { name } } } } } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new DepthLimiter(3)]);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('depth', strtolower($errors[0]->getMessage()));
    }

    public function testComplexityAllowsSimpleQuery(): void
    {
        $query = '{ user { name } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new ComplexityAnalyzer(100)]);
        self::assertEmpty($errors);
    }

    public function testComplexityBlocksExpensiveQuery(): void
    {
        // A query with many fields
        $query = '{ user { name friends { name friends { name friends { name } } } } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new ComplexityAnalyzer(3)]);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('complexity', strtolower($errors[0]->getMessage()));
    }

    public function testIntrospectionBlocksSchemaQuery(): void
    {
        // allowIntrospection=false blocks __schema
        $query = '{ __schema { types { name } } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new IntrospectionControl(allowIntrospection: false)]);
        self::assertNotEmpty($errors);
        self::assertStringContainsString('introspection', strtolower($errors[0]->getMessage()));
    }

    public function testIntrospectionAllowsNormalQuery(): void
    {
        $query = '{ user { name } }';
        $doc = Parser::parse($query);
        // default is allowIntrospection=true
        $errors = DocumentValidator::validate($this->schema, $doc, [new IntrospectionControl()]);
        self::assertEmpty($errors);
    }

    public function testIntrospectionAllowedByDefault(): void
    {
        $query = '{ __schema { types { name } } }';
        $doc = Parser::parse($query);
        $errors = DocumentValidator::validate($this->schema, $doc, [new IntrospectionControl()]);
        self::assertEmpty($errors);
    }
}
