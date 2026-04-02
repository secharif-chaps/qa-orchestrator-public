<?php

namespace App\Infrastructure\Shared;

trait NormalizeBoolTrait
{
    /**
     * Normalizes various boolean representations to a proper boolean value.
     * Returns null for invalid values.
     */
    private function normalizeBooleanValue(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $normalizedValue = strtolower(trim($value));

            if (\in_array($normalizedValue, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }

            if (\in_array($normalizedValue, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }

        if (\is_int($value)) {
            return 1 === $value;
        }

        return null; // Invalid value
    }
}
