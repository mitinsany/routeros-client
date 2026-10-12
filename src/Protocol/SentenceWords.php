<?php

namespace MitinSany\RouterOS\Protocol;

/**
 * Builds command words and extracts response attributes without changing their bytes.
 *
 * @internal
 */
final class SentenceWords
{
    /** @return array Ordered command, attribute and query words. */
    public static function command($command, $attributes, array $queryWords = [])
    {
        $words = [$command];
        foreach ($attributes as $key => $value) {
            $words[] = '=' . $key . '=' . $value;
        }
        foreach ($queryWords as $word) {
            $words[] = $word;
        }

        return $words;
    }

    /** @return array Response attributes, optionally excluding callback tags. */
    public static function attributes(array $sentence, $excludeTag = false)
    {
        $attributes = [];
        foreach ($sentence as $word) {
            if (!is_string($word) || substr($word, 0, 1) !== '=') {
                continue;
            }
            $parts = explode('=', substr($word, 1), 2);
            if (count($parts) === 2 && (!$excludeTag || $parts[0] !== '.tag')) {
                $attributes[$parts[0]] = $parts[1];
            }
        }

        return $attributes;
    }

    /** @return string|false First callback tag, accepting both legacy forms. */
    public static function tag(array $sentence)
    {
        foreach ($sentence as $word) {
            if (substr($word, 0, 6) === '=.tag=') {
                return substr($word, 6);
            }
            if (substr($word, 0, 5) === '.tag=') {
                return substr($word, 5);
            }
        }

        return false;
    }

    /** @return string|false Sentence type, or false for an empty sentence. */
    public static function type(array $sentence)
    {
        return isset($sentence[0]) ? $sentence[0] : false;
    }
}
