<?php

namespace App\Services\Games;

use App\Models\AuditLog;
use App\Models\GameQuestion;
use App\Models\User;
use App\Services\Ai\AiClient;

/**
 * Generates quiz questions with the configured AI provider.
 * - Prompt contains only the topic/difficulty — never queue or personal data.
 * - Output must pass QuestionSchema; otherwise nothing is saved.
 * - Saved items are `pending` (source=ai) until an admin approves them; only approved
 *   items are ever served. When AI is off, the local bank is used (QuestionBank).
 */
class QuestionGenerator
{
    public function __construct(private AiClient $ai)
    {
    }

    public function enabled(): bool
    {
        return $this->ai->enabled();
    }

    public function providerLabel(): string
    {
        return $this->ai->enabled() ? $this->ai->providerLabel() : 'none — using local bank';
    }

    /**
     * @return array{ok: bool, created: int, skipped: int, message: string}
     */
    public function generate(string $category, string $difficulty, int $count, ?User $actor = null): array
    {
        $count = max(1, min(5, $count));
        if (! array_key_exists($category, GameQuestion::CATEGORIES) || ! in_array($difficulty, GameQuestion::DIFFICULTIES, true)) {
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'message' => 'Unknown category or difficulty.'];
        }
        if (! $this->ai->enabled()) {
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'message' => 'AI provider: none — using local bank. No questions were generated.'];
        }

        $topic = GameQuestion::CATEGORIES[$category];
        $system = 'You write short, factual, family-friendly multiple-choice quiz questions for people waiting in a queue in Nepal. '
            .'Each question has exactly 4 distinct options and exactly one correct answer. Keep questions under 200 characters, options under 80, explanations under 300.';
        $prompt = "Write {$count} {$difficulty} questions on the topic \"{$topic}\". Return JSON exactly like: "
            .'{"questions":[{"question":"...","options":["...","...","...","..."],"answer":0,"explanation":"...","category":"'.$category.'","difficulty":"'.$difficulty.'"}]} '
            .'where "answer" is the 0-based index of the correct option.';

        $data = $this->ai->json($system, $prompt, fn ($d) => QuestionSchema::validPayload($d), 1200);
        if ($data === null) {
            return ['ok' => false, 'created' => 0, 'skipped' => 0, 'message' => 'The AI response was unavailable or failed schema validation, so nothing was saved. The local bank is still in use.'];
        }

        [$created, $skipped] = $this->store($data['questions']);
        AuditLog::record('games.questions_generated', null, ['created' => $created, 'skipped' => $skipped, 'category' => $category, 'by' => $actor?->id]);

        return ['ok' => true, 'created' => $created, 'skipped' => $skipped,
            'message' => "Generated {$created} question(s) for review".($skipped ? " ({$skipped} duplicate(s) skipped)" : '').'. Approve them before they are used.'];
    }

    /** Save already-validated items as pending AI questions. Returns [created, skipped]. */
    public function store(array $items): array
    {
        $created = $skipped = 0;
        foreach ($items as $item) {
            if (! QuestionSchema::validItem($item)) {
                $skipped++;

                continue;
            }
            $hash = GameQuestion::hashFor($item['question']);
            if (GameQuestion::where('hash', $hash)->exists()) {
                $skipped++;

                continue;
            }
            GameQuestion::create([
                'question' => trim($item['question']),
                'options' => array_map('trim', $item['options']),
                'answer_index' => $item['answer'],
                'explanation' => trim($item['explanation']),
                'category' => $item['category'],
                'difficulty' => $item['difficulty'],
                'source' => 'ai',
                'status' => 'pending',
                'hash' => $hash,
            ]);
            $created++;
        }

        return [$created, $skipped];
    }
}
