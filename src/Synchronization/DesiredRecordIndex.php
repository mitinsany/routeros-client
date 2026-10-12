<?php

declare(strict_types=1);

namespace MitinSany\RouterOS\Synchronization;

/**
 * Looks up desired records while preserving legacy matching and duplicate order.
 *
 * @internal
 */
final class DesiredRecordIndex
{
    private array $desired;
    private array $keys;
    private array $index;
    private array $matched = [];
    private array $cursors = [];

    public function __construct(array $desired, array $keys)
    {
        $this->desired = $desired;
        $this->keys = $keys;
        $this->index = $this->buildDesiredIndex($desired, $keys);
    }

    /**
     * Finds a desired index without consuming it; the caller records successful matches.
     *
     * @return int|string|false Desired array key, or false when no match is available.
     */
    public function find(array $record): int|string|false
    {
        $desired = $this->desired;
        $keys = $this->keys;
        $desiredIndex = $this->index;

        if (count($keys) === 0) {
            return false;
        }

        $matchKey = $this->matchKey($record, $keys);
        if ($matchKey !== false && isset($desiredIndex[$matchKey])) {
            $cursor = isset($this->cursors[$matchKey]) ? $this->cursors[$matchKey] : 0;
            $bucket = $desiredIndex[$matchKey];
            while (isset($bucket[$cursor])) {
                $index = $bucket[$cursor];
                if (isset($this->matched[$index])) {
                    $cursor++;
                    $this->cursors[$matchKey] = $cursor;
                    continue;
                }
                if ($this->keysMatch($record, $desired[$index], $keys)) {
                    return $index;
                }
                $cursor++;
            }
        }

        if (!$this->needsLegacyKeyFallback($record, $keys)) {
            return false;
        }

        foreach ($desired as $index => $attributes) {
            if (isset($this->matched[$index])) {
                continue;
            }
            if ($this->keysMatch($record, $attributes, $keys)) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Excludes a desired record after matching a static record with a non-null identifier.
     *
     * Dynamic records do not claim a slot because a static record still needs to be added.
     */
    public function markMatched(int|string $index): void
    {
        $this->matched[$index] = true;
    }

    private function buildDesiredIndex(array $desired, array $keys): array
    {
        $index = [];
        if (count($keys) === 0) {
            return $index;
        }

        foreach ($desired as $position => $attributes) {
            $matchKey = $this->matchKey($attributes, $keys);
            if ($matchKey === false) {
                continue;
            }
            if (!isset($index[$matchKey])) {
                $index[$matchKey] = [];
            }
            $index[$matchKey][] = $position;
        }

        return $index;
    }

    private function matchKey(array $attributes, array $keys): string|false
    {
        $values = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $attributes)) {
                return false;
            }
            $values[] = ValueComparison::normalise($attributes[$key]);
        }

        return serialize($values);
    }

    private function keysMatch(array $record, array $attributes, array $keys): bool
    {
        foreach ($keys as $key) {
            if (
                !array_key_exists($key, $record)
                || !array_key_exists($key, $attributes)
                || !ValueComparison::equivalent($record[$key], $attributes[$key])
            ) {
                return false;
            }
        }

        return true;
    }

    private function needsLegacyKeyFallback(array $record, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $record)) {
                return false;
            }
            $value = ValueComparison::normalise($record[$key]);
            if ($value === '' || $value === 'false' || $value === 'no') {
                return true;
            }
        }

        return false;
    }
}
