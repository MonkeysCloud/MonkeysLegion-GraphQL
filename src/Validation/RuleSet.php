<?php declare(strict_types=1);

namespace MonkeysLegion\GraphQL\Validation;

/**
 * Defines validation rules for input fields.
 */
final class RuleSet
{
    /** @var array<string, array<string>> field => [rules] */
    private array $rules = [];

    /**
     * Create a RuleSet from an associative array.
     *
     * @param array<string, string|array<string>> $rules Field-name => rules (string pipe-delimited or array)
     *
     * @return self
     */
    public static function fromArray(array $rules): self
    {
        $instance = new self();

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }
            $instance->rules[$field] = $fieldRules;
        }

        return $instance;
    }

    /**
     * Add a rule for a field.
     *
     * @param string $field The field name
     * @param string $rule  The rule to add
     *
     * @return self
     */
    public function add(string $field, string $rule): self
    {
        $this->rules[$field] ??= [];
        $this->rules[$field][] = $rule;
        return $this;
    }

    /**
     * Set all rules for a field (replaces existing).
     *
     * @param string        $field The field name
     * @param array<string> $rules Rules to set
     *
     * @return self
     */
    public function set(string $field, array $rules): self
    {
        $this->rules[$field] = $rules;
        return $this;
    }

    /**
     * Get all rules.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return $this->rules;
    }

    /**
     * Get rules for a specific field.
     *
     * @param string $field The field name
     *
     * @return array<string>
     */
    public function forField(string $field): array
    {
        return $this->rules[$field] ?? [];
    }

    /**
     * Check if any rules are defined.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->rules === [];
    }
}
