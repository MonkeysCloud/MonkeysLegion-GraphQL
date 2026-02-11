<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Builder;

use GraphQL\Type\Definition\EnumType;
use MonkeysLegion\GraphQL\Attribute\Enum;
use ReflectionClass;
use ReflectionEnum;
use UnitEnum;

/**
 * Builds webonyx EnumType definitions from PHP backed enums annotated with #[Enum].
 */
final class EnumBuilder
{
    /**
     * Build a webonyx EnumType from a PHP enum class.
     *
     * @param class-string      $className  The enum class name
     * @param ReflectionClass<object>  $reflection Reflection of the enum class
     * @param Enum              $attribute  The #[Enum] attribute instance
     *
     * @return EnumType
     */
    public function build(string $className, ReflectionClass $reflection, Enum $attribute): EnumType
    {
        $enumReflection = new ReflectionEnum($className);
        $name = $attribute->name ?? $reflection->getShortName();

        $values = [];
        foreach ($enumReflection->getCases() as $case) {
            /** @var \ReflectionEnumBackedCase|\ReflectionEnumUnitCase $case */
            $caseName = $case->getName();
            $value = [
                'value' => $case instanceof \ReflectionEnumBackedCase
                    ? $case->getBackingValue()
                    : $caseName,
            ];

            // Check for description via docblock
            $doc = $case->getDocComment();
            if ($doc !== false) {
                $description = $this->extractDescription($doc);
                if ($description !== null) {
                    $value['description'] = $description;
                }
            }

            // Check for deprecation via #[Deprecated] attribute
            $deprecatedAttrs = $case->getAttributes(\Deprecated::class);
            if ($deprecatedAttrs !== []) {
                $value['deprecationReason'] = 'Deprecated';
            }

            $values[$caseName] = $value;
        }

        return new EnumType([
            'name'        => $name,
            'description' => $attribute->description,
            'values'      => $values,
        ]);
    }

    /**
     * Extract description from a docblock comment.
     *
     * @param string $docComment The raw docblock comment
     *
     * @return string|null
     */
    private function extractDescription(string $docComment): ?string
    {
        // Remove /** and */
        $clean = preg_replace('/^\s*\/\*\*\s*|\s*\*\/\s*$/s', '', $docComment);
        if ($clean === null) {
            return null;
        }

        // Remove leading * on each line
        $lines = array_map(
            static fn(string $line): string => preg_replace('/^\s*\*\s?/', '', $line) ?? '',
            explode("\n", $clean),
        );

        // Take lines before the first @tag
        $description = [];
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '@')) {
                break;
            }
            $description[] = trim($line);
        }

        $result = trim(implode(' ', $description));
        return $result !== '' ? $result : null;
    }
}
