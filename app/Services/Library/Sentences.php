<?php

namespace App\Services\Library;

/**
 * Splits chapter text into sentences for the audiobook ("Listen") mode.
 * The server renders every sentence as its own <span data-s="N">, so the browser's
 * speech engine, the highlighting and the saved listening index all use the SAME numbering.
 */
class Sentences
{
    /** Words that end in a full stop but don't end a sentence ("Mr. Holmes", "8:40 p.m. bus"). */
    private const ABBREV = '/(?:^|\s|\()(?:Mr|Mrs|Ms|Dr|St|Jr|Sr|Mt|Prof|No|vs|etc|approx|e\.g|i\.e|a\.m|p\.m|[A-Z])\.$/u';

    /** @return list<string> */
    public static function split(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }

        // Up to and including terminal punctuation (. ! ? … and the Devanagari danda ।) plus closing quotes/brackets.
        preg_match_all('/.+?(?:[.!?…।॥]+["\'”’»)\]]*(?=\s|$)|$)/u', $text, $m);
        $out = [];
        $carry = '';
        foreach ($m[0] as $piece) {
            $piece = trim($piece);
            if ($piece === '') {
                continue;
            }
            $candidate = $carry === '' ? $piece : $carry.' '.$piece;
            if (preg_match(self::ABBREV, $candidate)) {
                $carry = $candidate; // keep going: "Mr." is not the end of a sentence

                continue;
            }
            $out[] = $candidate;
            $carry = '';
        }
        if ($carry !== '') {
            $out[] = $carry;
        }

        return $out;
    }

    /** Rough spoken length at 1× (150 words per minute). */
    public static function listeningMinutes(int $words): int
    {
        return max(1, (int) ceil($words / 150));
    }
}
