<?php

declare(strict_types=1);

namespace Relova\Services;

use Relova\Exceptions\CustomFieldValidationException;
use Relova\Models\CustomFieldDefinition;

/**
 * Validates a value against a CustomFieldDefinition before any write.
 *
 * Rules enforced: required check, text min/max length, text regex pattern,
 * number min/max value. Validation lives entirely in PHP since EAV typed
 * columns cannot enforce NOT NULL at the database level.
 */
class CustomFieldValidator
{
    /**
     * Validate a value against the given field definition.
     *
     * @throws CustomFieldValidationException
     */
    public function validate(CustomFieldDefinition $definition, mixed $value): void
    {
        $errors = [];
        $fieldName = $definition->name;

        // Required check
        if ($definition->is_required && $this->isEmpty($value, $definition->field_type)) {
            $errors[$fieldName] = "The {$definition->label} field is required.";

            // No point validating further if empty and required
            throw new CustomFieldValidationException(
                "Validation failed for field '{$fieldName}'.",
                $errors,
            );
        }

        // Skip further validation if value is empty and not required
        if ($this->isEmpty($value, $definition->field_type)) {
            return;
        }

        match ($definition->field_type) {
            'text' => $this->validateText($definition, $value, $errors),
            'number' => $this->validateNumber($definition, $value, $errors),
            'date' => $this->validateDate($definition, $value, $errors),
            'boolean' => $this->validateBoolean($definition, $value, $errors),
            default => null,
        };

        if ($errors !== []) {
            throw new CustomFieldValidationException(
                "Validation failed for field '{$fieldName}'.",
                $errors,
            );
        }
    }

    private function isEmpty(mixed $value, string $fieldType): bool
    {
        if ($value === null) {
            return true;
        }

        if ($fieldType === 'boolean') {
            return false; // false is a valid boolean value
        }

        if ($fieldType === 'text' && $value === '') {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateText(CustomFieldDefinition $definition, mixed $value, array &$errors): void
    {
        $fieldName = $definition->name;

        if (! is_string($value)) {
            $errors[$fieldName] = "The {$definition->label} field must be a string.";

            return;
        }

        $length = mb_strlen($value);

        if ($definition->min_length !== null && $length < $definition->min_length) {
            $errors[$fieldName] = "The {$definition->label} field must be at least {$definition->min_length} characters.";

            return;
        }

        if ($definition->max_length !== null && $length > $definition->max_length) {
            $errors[$fieldName] = "The {$definition->label} field must not exceed {$definition->max_length} characters.";

            return;
        }

        if ($definition->regex_pattern !== null && preg_match($definition->regex_pattern, $value) !== 1) {
            $errors[$fieldName] = "The {$definition->label} field format is invalid.";
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateNumber(CustomFieldDefinition $definition, mixed $value, array &$errors): void
    {
        $fieldName = $definition->name;

        if (! is_numeric($value)) {
            $errors[$fieldName] = "The {$definition->label} field must be a number.";

            return;
        }

        $numericValue = (float) $value;

        if ($definition->min_value !== null && $numericValue < $definition->min_value) {
            $errors[$fieldName] = "The {$definition->label} field must be at least {$definition->min_value}.";

            return;
        }

        if ($definition->max_value !== null && $numericValue > $definition->max_value) {
            $errors[$fieldName] = "The {$definition->label} field must not exceed {$definition->max_value}.";
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateDate(CustomFieldDefinition $definition, mixed $value, array &$errors): void
    {
        $fieldName = $definition->name;

        if (! is_string($value) && ! $value instanceof \DateTimeInterface) {
            $errors[$fieldName] = "The {$definition->label} field must be a valid date.";

            return;
        }

        if (is_string($value)) {
            $parsed = date_create($value);

            if ($parsed === false) {
                $errors[$fieldName] = "The {$definition->label} field must be a valid date.";
            }
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateBoolean(CustomFieldDefinition $definition, mixed $value, array &$errors): void
    {
        $fieldName = $definition->name;

        if (! is_bool($value) && $value !== 0 && $value !== 1 && $value !== '0' && $value !== '1') {
            $errors[$fieldName] = "The {$definition->label} field must be true or false.";
        }
    }
}
