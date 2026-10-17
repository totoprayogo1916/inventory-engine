<?php

declare(strict_types=1);

namespace Ttpryg\InventoryEngine\Support;

use DateTimeImmutable;
use UnexpectedValueException;

/**
 * Typed accessors for PDO FETCH_ASSOC rows, whose values arrive as mixed.
 *
 * @internal
 */
final class PdoRow
{
    private function __construct() {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new UnexpectedValueException("Column '{$key}' does not contain a string value.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function int(array $row, string $key, ?int $default = null): int
    {
        $value = $row[$key] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || (is_string($value) && is_numeric($value))) {
            return (int) $value;
        }

        if ($default !== null) {
            return $default;
        }

        throw new UnexpectedValueException("Column '{$key}' does not contain an integer value.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function bool(array $row, string $key): bool
    {
        $value = $row[$key] ?? null;

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            return $value !== '' && $value !== '0';
        }

        throw new UnexpectedValueException("Column '{$key}' does not contain a boolean value.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new UnexpectedValueException("Column '{$key}' does not contain a string value.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function dateTime(array $row, string $key): ?DateTimeImmutable
    {
        $value = $row[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        throw new UnexpectedValueException("Column '{$key}' does not contain a datetime value.");
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function dateTimeNotNull(array $row, string $key): DateTimeImmutable
    {
        $value = self::dateTime($row, $key);

        if (! $value instanceof \DateTimeImmutable) {
            throw new UnexpectedValueException("Column '{$key}' does not contain a datetime value.");
        }

        return $value;
    }
}
