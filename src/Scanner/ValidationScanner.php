<?php
declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Scanner;

use MonkeysLegion\Entity\Attributes\Fillable;
use MonkeysLegion\GraphQL\Attribute\Validate;
use MonkeysLegion\GraphQL\Validation\RuleSet;
use MonkeysLegion\Validation\Attributes as Assert;
use ReflectionClass;

/**
 * MonkeysLegion Framework — GraphQL Package
 *
 * Scans entity properties for #[Validate] attributes and
 * MonkeysLegion Validation attributes (#[NotBlank], etc.)
 * to automatically build RuleSets for mutation inputs.
 *
 * @copyright 2026 MonkeysCloud Team
 * @license   MIT
 */
final class ValidationScanner
{
    /** @var array<class-string, array{create: RuleSet, update: RuleSet}> */
    private array $cache = [];

    /**
     * Build RuleSets for a given entity class.
     *
     * @param class-string $entityClass
     *
     * @return array{create: RuleSet, update: RuleSet}
     */
    public function scan(string $entityClass): array
    {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);

        $createRules = RuleSet::fromArray([]);
        $updateRules = RuleSet::fromArray([]);

        foreach ($reflection->getProperties() as $property) {
            // Only process #[Fillable] properties
            if ($property->getAttributes(Fillable::class) === []) {
                continue;
            }

            $propName = $property->getName();
            $rules = [];

            // Read #[Validate] attributes
            foreach ($property->getAttributes(Validate::class) as $attr) {
                /** @var Validate $validate */
                $validate = $attr->newInstance();
                $rules = array_merge($rules, $validate->rules);
            }

            // Read MonkeysLegion Validation attributes for auto-rule inference
            if ($property->getAttributes(Assert\NotBlank::class) !== []) {
                if (!in_array('required', $rules, true)) {
                    $rules[] = 'required';
                }
            }

            if ($property->getAttributes(Assert\Email::class) !== []) {
                if (!in_array('email', $rules, true)) {
                    $rules[] = 'email';
                }
            }

            if ($property->getAttributes(Assert\Url::class) !== []) {
                if (!in_array('url', $rules, true)) {
                    $rules[] = 'url';
                }
            }

            // Check for min/max from Assert\Length
            foreach ($property->getAttributes(Assert\Length::class) as $lenAttr) {
                $length = $lenAttr->newInstance();
                if (property_exists($length, 'min') && $length->min !== null) {
                    $rules[] = "min_length:{$length->min}";
                }
                if (property_exists($length, 'max') && $length->max !== null) {
                    $rules[] = "max_length:{$length->max}";
                }
            }

            if ($rules !== []) {
                foreach ($rules as $rule) {
                    $createRules->add($propName, $rule);

                    // For updates, skip 'required' — all fields are optional
                    if ($rule !== 'required') {
                        $updateRules->add($propName, $rule);
                    }
                }
            }
        }

        $result = [
            'create' => $createRules,
            'update' => $updateRules,
        ];

        $this->cache[$entityClass] = $result;
        return $result;
    }
}
