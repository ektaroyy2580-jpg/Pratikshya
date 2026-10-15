<?php

namespace App\Services\Games;

use App\Models\GameQuestion;

/**
 * Strict schema for AI-generated multiple-choice questions.
 * A payload is accepted only if EVERY item is valid — one bad item rejects the batch.
 *
 * {"questions":[{"question":"...","options":["a","b","c","d"],"answer":0,"explanation":"...","category":"logic","difficulty":"easy"}]}
 */
class QuestionSchema
{
    public const MAX_QUESTION = 200;

    public const MAX_OPTION = 80;

    public const MAX_EXPLANATION = 300;

    public const MAX_ITEMS = 10;

    public static function validPayload(mixed $data): bool
    {
        if (! is_array($data) || ! isset($data['questions']) || ! is_array($data['questions']) || ! array_is_list($data['questions'])) {
            return false;
        }
        $items = $data['questions'];
        if ($items === [] || count($items) > self::MAX_ITEMS) {
            return false;
        }
        foreach ($items as $item) {
            if (! self::validItem($item)) {
                return false;
            }
        }

        return true;
    }

    public static function validItem(mixed $item): bool
    {
        if (! is_array($item)) {
            return false;
        }
        $allowed = ['question', 'options', 'answer', 'explanation', 'category', 'difficulty'];
        if (array_diff(array_keys($item), $allowed) !== [] || count(array_intersect(array_keys($item), $allowed)) !== count($allowed)) {
            return false; // exactly these keys
        }

        $q = $item['question'];
        if (! is_string($q) || mb_strlen(trim($q)) < 8 || mb_strlen($q) > self::MAX_QUESTION) {
            return false;
        }

        $opts = $item['options'];
        if (! is_array($opts) || ! array_is_list($opts) || count($opts) !== 4) {
            return false;
        }
        foreach ($opts as $o) {
            if (! is_string($o) || trim($o) === '' || mb_strlen($o) > self::MAX_OPTION) {
                return false;
            }
        }
        if (count(array_unique(array_map(fn ($o) => mb_strtolower(trim($o)), $opts))) !== 4) {
            return false; // duplicate options
        }

        if (! is_int($item['answer']) || $item['answer'] < 0 || $item['answer'] > 3) {
            return false;
        }

        $exp = $item['explanation'];
        if (! is_string($exp) || trim($exp) === '' || mb_strlen($exp) > self::MAX_EXPLANATION) {
            return false;
        }

        if (! is_string($item['category']) || ! array_key_exists($item['category'], GameQuestion::CATEGORIES)) {
            return false;
        }

        return is_string($item['difficulty']) && in_array($item['difficulty'], GameQuestion::DIFFICULTIES, true);
    }
}
