<?php

namespace App\Services\Arcade;

use App\Models\ArcadeSession;

/**
 * Shared engine for multiple-choice games (GK quiz, cyber safety, logic).
 * Correct answers + explanations stay in the server payload; each answer is
 * locked the moment it is submitted, and the score is computed server-side.
 */
abstract class QuestionGame extends BaseGame
{
    /** Number of questions per round. */
    protected int $rounds = 8;

    /** Client-side countdown per question (seconds). */
    protected int $secondsPerQuestion = 25;

    /** Bank entries: ['q', 'options' => [...], 'answer' => int, 'explain', ...extra]. */
    abstract protected function bank(): array;

    /** Extra, schema-validated questions (e.g. from AI). Same shape as bank(). */
    protected function extraQuestions(): array
    {
        return [];
    }

    /** Extra client fields copied from a bank entry (e.g. message channel). */
    protected function clientMeta(array $item): array
    {
        return [];
    }

    protected function shuffleOptions(): bool
    {
        return true;
    }

    public function start(string $variant): array
    {
        $extra = array_slice($this->extraQuestions(), 0, 2);
        $chosen = array_merge($extra, $this->pick($this->bank(), $this->rounds - count($extra)));
        $chosen = $this->shuffle($chosen);

        $payload = ['questions' => [], 'answers' => []];
        $client = ['seconds' => $this->secondsPerQuestion, 'questions' => []];
        foreach ($chosen as $i => $item) {
            $order = array_keys($item['options']);
            if ($this->shuffleOptions()) {
                $order = $this->shuffle($order);
            }
            $options = array_map(fn ($k) => $item['options'][$k], $order);
            $correct = array_search($item['answer'], $order, true);

            $payload['questions'][] = [
                'q' => $item['q'], 'options' => $options, 'answer' => $correct,
                'explain' => $item['explain'], 'flags' => $item['flags'] ?? [],
            ];
            $client['questions'][] = array_merge([
                'index' => $i, 'q' => $item['q'], 'options' => $options, 'source' => $item['source'] ?? 'bank',
            ], $this->clientMeta($item));
        }

        return ['payload' => $payload, 'client' => $client];
    }

    public function interact(ArcadeSession $session, array $input): array
    {
        $p = $session->payload;
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        $total = count($p['questions']);
        if ($index === false || $index < 0 || $index >= $total) {
            throw new ArcadeRejected('Unknown question.', false);
        }
        if (array_key_exists($index, $p['answers'] ?? [])) {
            throw new ArcadeRejected('You already answered this question.', false, 409);
        }
        if ($index !== count($p['answers'] ?? [])) {
            throw new ArcadeRejected('Answer the questions in order.', false);
        }

        $q = $p['questions'][$index];
        $choice = $input['choice'] ?? null;               // null = time ran out
        $choice = $choice === null ? null : filter_var($choice, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => count($q['options']) - 1]]);
        if ($choice === false) {
            throw new ArcadeRejected('Invalid option.', false);
        }
        $correct = $choice !== null && $choice === $q['answer'];
        $p['answers'][$index] = ['choice' => $choice, 'correct' => $correct, 'at' => $session->elapsedMs()];

        [$score] = $this->tally($p);

        return [$p, [
            'correct' => $correct,
            'answer' => $q['answer'],
            'explain' => $q['explain'],
            'flags' => $q['flags'],
            'score' => $score,
            'answered' => count($p['answers']),
            'total' => $total,
        ]];
    }

    /** @return array{0:int,1:int,2:int} score, correct, best streak */
    protected function tally(array $p): array
    {
        $score = $correct = $streak = $best = 0;
        foreach ($p['answers'] ?? [] as $a) {
            if ($a['correct']) {
                $correct++;
                $streak++;
                $best = max($best, $streak);
                $score += 100 + ($streak > 1 ? 20 * ($streak - 1) : 0);
            } else {
                $streak = 0;
            }
        }

        return [$score, $correct, $best];
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $p = $session->payload;
        $total = count($p['questions']);
        $answered = count($p['answers'] ?? []);
        if ($answered < $total) {
            throw new ArcadeRejected('Answer every question first ('.$answered.'/'.$total.').', false);
        }
        $this->assertMinElapsed($session, $total * 800);

        [$score, $correct, $best] = $this->tally($p);
        $points = (int) round($correct / max(1, $total) * 16) + ($correct === $total ? 4 : 0);

        return new Outcome($score, $points, true, [
            'headline' => $correct.' / '.$total.' correct',
            'stats' => [
                ['label' => 'Correct', 'value' => $correct.'/'.$total],
                ['label' => 'Best streak', 'value' => (string) $best],
                ['label' => 'Accuracy', 'value' => round($correct / max(1, $total) * 100).'%'],
            ],
        ]);
    }
}
