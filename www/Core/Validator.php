<?php

namespace App\Core;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);
            $value = $data[$field] ?? null;

            foreach ($ruleList as $rule) {
                $params = [];

                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = $this->validateRule($field, $value, $rule, $params, $data);
                if ($error) {
                    $this->errors[$field][] = $error;
                    break;
                }
            }
        }

        return empty($this->errors);
    }

    private function validateRule(string $field, mixed $value, string $rule, array $params, array $data): ?string
    {
        $label = str_replace(['_', '-'], ' ', ucfirst($field));

        return match ($rule) {
            'required' => ($value === null || $value === '') ? "{$label} is required." : null,
            'email' => (!filter_var($value, FILTER_VALIDATE_EMAIL)) ? "{$label} must be a valid email." : null,
            'min' => (is_string($value) && strlen($value) < (int)($params[0] ?? 1)) ? "{$label} must be at least {$params[0]} characters." : null,
            'max' => (is_string($value) && strlen($value) > (int)($params[0] ?? 255)) ? "{$label} must not exceed {$params[0]} characters." : null,
            'confirmed' => (($data[$field . '_confirmation'] ?? null) !== $value) ? "{$label} confirmation does not match." : null,
            'unique' => $this->validateUnique($field, $value, $params),
            'exists' => $this->validateExists($field, $value, $params),
            'numeric' => (!is_numeric($value)) ? "{$label} must be a number." : null,
            'in' => (!in_array((string)$value, $params)) ? "{$label} is invalid." : null,
            default => null,
        };
    }

    private function validateUnique(string $field, mixed $value, array $params): ?string
    {
        $table = $params[0] ?? $field;
        $column = $params[1] ?? $field;
        $except = $params[2] ?? null;

        $sql = "SELECT id FROM {$table} WHERE {$column} = ?";
        $bindings = [$value];

        if ($except) {
            $sql .= " AND id != ?";
            $bindings[] = $except;
        }

        $sql .= " AND archived_at IS NULL";

        $exists = Database::fetch($sql, $bindings);
        return $exists ? ucfirst(str_replace('_', ' ', $field)) . ' already exists.' : null;
    }

    private function validateExists(string $field, mixed $value, array $params): ?string
    {
        $table = $params[0] ?? $field;
        $column = $params[1] ?? $field;

        $exists = Database::fetch(
            "SELECT id FROM {$table} WHERE {$column} = ?",
            [$value]
        );
        return !$exists ? ucfirst(str_replace('_', ' ', $field)) . ' does not exist.' : null;
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function firstError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
