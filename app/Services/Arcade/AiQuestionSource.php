<?php

namespace App\Services\Arcade;

use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Cache;

/**
 * Optional AI-generated question variations. Only the topic is sent (never personal or
 * queue data). Output is schema-validated, approved items are cached, and when AI is off
 * or returns anything invalid we return [] so the local bank is used.
 */
class AiQuestionSource
{
    public function __construct(private AiClient $ai) {}

    public function questions(string $gameKey, string $topic, int $count = 2): array
    {
        if (! config('arcade.ai_variations') || ! $this->ai->enabled()) {
            return [];
        }

        $cached = Cache::get('arcade:ai:'.$gameKey);
        if (! is_array($cached)) {
            $data = $this->ai->json(
                'You write short, factual multiple-choice questions for a family-friendly waiting-room game. No personal data.',
                "Write 6 questions about: {$topic}. Return {\"questions\":[{\"q\":string,\"options\":[4 strings],\"answer\":index 0-3,\"explain\":string}]}",
                fn (array $d) => $this->valid($d),
                900,
            );
            $cached = $data ? array_map(fn ($q) => [
                'q' => trim($q['q']), 'options' => array_map('trim', $q['options']),
                'answer' => (int) $q['answer'], 'explain' => trim($q['explain']), 'source' => 'ai',
            ], $data['questions']) : [];
            Cache::put('arcade:ai:'.$gameKey, $cached, now()->addHours($cached ? 6 : 1));
        }

        return array_slice((new \Random\Randomizer)->shuffleArray($cached), 0, $count);
    }

    /** Schema check: every item must be complete, short, with 4 distinct options and a valid answer index. */
    public function valid(array $d): bool
    {
        if (! isset($d['questions']) || ! is_array($d['questions']) || count($d['questions']) < 1) {
            return false;
        }
        foreach ($d['questions'] as $q) {
            if (! is_array($q) || ! is_string($q['q'] ?? null) || mb_strlen(trim($q['q'])) < 8 || mb_strlen($q['q']) > 220) {
                return false;
            }
            $opts = $q['options'] ?? null;
            if (! is_array($opts) || count($opts) !== 4 || ! array_is_list($opts)) {
                return false;
            }
            foreach ($opts as $o) {
                if (! is_string($o) || trim($o) === '' || mb_strlen($o) > 90) {
                    return false;
                }
            }
            if (count(array_unique(array_map(fn ($o) => mb_strtolower(trim($o)), $opts))) !== 4) {
                return false;
            }
            if (! is_int($q['answer'] ?? null) || $q['answer'] < 0 || $q['answer'] > 3) {
                return false;
            }
            if (! is_string($q['explain'] ?? null) || mb_strlen($q['explain']) > 320) {
                return false;
            }
        }

        return true;
    }
}
