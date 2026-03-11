<?php

/**
 * Static input validation helpers.
 */
class Validator
{
    /**
     * Returns an array of field names that are missing or empty in $data.
     */
    public static function required(array $data, array $fields): array
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    /**
     * Validates email format.
     */
    public static function email(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validates minimum string length.
     */
    public static function minLength(string $value, int $min): bool
    {
        return strlen($value) >= $min;
    }
}
