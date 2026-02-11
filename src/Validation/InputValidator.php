<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Validation;

use MonkeysLegion\GraphQL\Error\ValidationError;

/**
 * Input argument validator for GraphQL mutations.
 *
 * Applies rule sets to input data before resolver execution.
 */
final class InputValidator
{
    /**
     * Validate input data against a rule set.
     *
     * @param array<string, mixed> $data  The input data to validate
     * @param RuleSet              $rules The rule set to apply
     *
     * @return void
     *
     * @throws ValidationError If validation fails
     */
    public function validate(array $data, RuleSet $rules): void
    {
        $errors = [];

        foreach ($rules->rules() as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $error = $this->applyRule($rule, $field, $value, $data);
                if ($error !== null) {
                    $errors[$field] ??= [];
                    $errors[$field][] = $error;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationError('Validation failed', $errors);
        }
    }

    /**
     * Apply a single rule to a value.
     *
     * @param string               $rule  The rule definition (e.g., "required", "min:3")
     * @param string               $field The field name
     * @param mixed                $value The field value
     * @param array<string, mixed> $data  Full input data for cross-field validation
     *
     * @return string|null Error message if validation fails, null if passes
     */
    private function applyRule(string $rule, string $field, mixed $value, array $data): ?string
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        return match ($ruleName) {
            'required' => $this->ruleRequired($field, $value),
            'string'   => $this->ruleString($field, $value),
            'int', 'integer' => $this->ruleInteger($field, $value),
            'float', 'numeric' => $this->ruleNumeric($field, $value),
            'bool', 'boolean' => $this->ruleBoolean($field, $value),
            'email'    => $this->ruleEmail($field, $value),
            'url'      => $this->ruleUrl($field, $value),
            'min'      => $this->ruleMin($field, $value, (int) ($parameter ?? '0')),
            'max'      => $this->ruleMax($field, $value, (int) ($parameter ?? '0')),
            'min_length' => $this->ruleMinLength($field, $value, (int) ($parameter ?? '0')),
            'max_length' => $this->ruleMaxLength($field, $value, (int) ($parameter ?? '0')),
            'in'       => $this->ruleIn($field, $value, $parameter ?? ''),
            'regex'    => $this->ruleRegex($field, $value, $parameter ?? ''),
            'confirmed' => $this->ruleConfirmed($field, $value, $data),
            default    => null, // Unknown rules are skipped
        };
    }

    private function ruleRequired(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return "The {$field} field is required.";
        }
        return null;
    }

    private function ruleString(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_string($value)) {
            return "The {$field} field must be a string.";
        }
        return null;
    }

    private function ruleInteger(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_int($value)) {
            return "The {$field} field must be an integer.";
        }
        return null;
    }

    private function ruleNumeric(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_numeric($value)) {
            return "The {$field} field must be numeric.";
        }
        return null;
    }

    private function ruleBoolean(string $field, mixed $value): ?string
    {
        if ($value !== null && !is_bool($value)) {
            return "The {$field} field must be a boolean.";
        }
        return null;
    }

    private function ruleEmail(string $field, mixed $value): ?string
    {
        if ($value !== null && (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false)) {
            return "The {$field} field must be a valid email address.";
        }
        return null;
    }

    private function ruleUrl(string $field, mixed $value): ?string
    {
        if ($value !== null && (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false)) {
            return "The {$field} field must be a valid URL.";
        }
        return null;
    }

    private function ruleMin(string $field, mixed $value, int $min): ?string
    {
        if ($value !== null && is_numeric($value) && $value < $min) {
            return "The {$field} field must be at least {$min}.";
        }
        return null;
    }

    private function ruleMax(string $field, mixed $value, int $max): ?string
    {
        if ($value !== null && is_numeric($value) && $value > $max) {
            return "The {$field} field must not be greater than {$max}.";
        }
        return null;
    }

    private function ruleMinLength(string $field, mixed $value, int $min): ?string
    {
        if ($value !== null && is_string($value) && mb_strlen($value) < $min) {
            return "The {$field} field must be at least {$min} characters.";
        }
        return null;
    }

    private function ruleMaxLength(string $field, mixed $value, int $max): ?string
    {
        if ($value !== null && is_string($value) && mb_strlen($value) > $max) {
            return "The {$field} field must not be greater than {$max} characters.";
        }
        return null;
    }

    private function ruleIn(string $field, mixed $value, string $options): ?string
    {
        $allowed = explode(',', $options);
        if ($value !== null && !in_array((string) $value, $allowed, true)) {
            return "The {$field} field must be one of: {$options}.";
        }
        return null;
    }

    private function ruleRegex(string $field, mixed $value, string $pattern): ?string
    {
        if ($value !== null && is_string($value) && !preg_match($pattern, $value)) {
            return "The {$field} field format is invalid.";
        }
        return null;
    }

    private function ruleConfirmed(string $field, mixed $value, array $data): ?string
    {
        $confirmField = $field . '_confirmation';
        if ($value !== null && (!isset($data[$confirmField]) || $data[$confirmField] !== $value)) {
            return "The {$field} confirmation does not match.";
        }
        return null;
    }
}
