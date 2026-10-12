<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Synchronization;

/**
 * Legacy RouterOS value comparison shared by diffs and record matching.
 *
 * @internal
 */
final class ValueComparison
{
    /** @return bool Whether the two values compare equal under legacy rules. */
    public static function equivalent($left, $right): bool
    {
        $left = self::normalise($left);
        $right = self::normalise($right);
        if ($left === $right) {
            return true;
        }

        return ($left === '' && ($right === 'false' || $right === 'no'))
            || ($right === '' && ($left === 'false' || $left === 'no'));
    }

    /** @return string Normalized value, preserving binary input. */
    public static function normalise($value): string
    {
        if ($value === false) {
            $value = 'false';
        } elseif ($value === true) {
            $value = 'true';
        } elseif ($value === null) {
            $value = '';
        } else {
            $value = (string) $value;
        }

        return str_replace(';', ',', $value);
    }

    /** @return array Desired fields that are absent or different in the current record. */
    public static function changes(array $current, array $desired): array
    {
        $changes = [];
        foreach ($desired as $key => $value) {
            if (!array_key_exists($key, $current) || !self::equivalent($current[$key], $value)) {
                $changes[$key] = $value;
            }
        }

        return $changes;
    }
}
