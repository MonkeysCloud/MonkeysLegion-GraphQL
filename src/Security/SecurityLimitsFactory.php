<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Security;

use GraphQL\Validator\Rules\ValidationRule;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Factory that builds the complete set of security validation rules
 * for the GraphQL executor. Configurable via the application's
 * graphql configuration section.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class SecurityLimitsFactory
{
    /**
     * Build validation rules from configuration.
     *
     * @param array{
     *     maxDepth?: int,
     *     maxComplexity?: int,
     *     fieldCosts?: array<string, int>,
     *     defaultCost?: int,
     *     introspection?: bool
     * } $config
     *
     * @return list<ValidationRule>
     */
    public static function fromConfig(array $config): array
    {
        $rules = [];

        // Depth Limiting
        $maxDepth = $config['maxDepth'] ?? 0;
        if ($maxDepth > 0) {
            $rules[] = new DepthLimiter($maxDepth);
        }

        // Complexity Analysis
        $maxComplexity = $config['maxComplexity'] ?? 0;
        if ($maxComplexity > 0) {
            $rules[] = new ComplexityAnalyzer(
                maxComplexity: $maxComplexity,
                fieldCosts: $config['fieldCosts'] ?? [],
                defaultCost: $config['defaultCost'] ?? 1,
            );
        }

        // Introspection Control
        $introspectionEnabled = $config['introspection'] ?? true;
        if (!$introspectionEnabled) {
            $rules[] = new IntrospectionControl();
        }

        return $rules;
    }
}
