<?php

namespace App\Services\Arcade\Games;

use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Arcade\BaseGame;
use App\Services\Arcade\Outcome;

/**
 * Reaction Challenge — 5 rounds. The server picks the random "go" delays; reported times must
 * be within human range (100–2000 ms) and the session must have lasted at least as long as
 * the delays plus the reactions.
 */
class ReactionGame extends BaseGame
{
    public const ROUNDS = 5;
    public const MIN_MS = 100;   // faster than ~100 ms is anticipation, not reaction
    public const MAX_MS = 2000;

    public function key(): string
    {
        return 'reaction';
    }

    public function start(string $variant): array
    {
        $delays = [];
        for ($i = 0; $i < self::ROUNDS; $i++) {
            $delays[] = random_int(1200, 4000);
        }

        return ['payload' => ['delays' => $delays], 'client' => ['delays' => $delays, 'rounds' => self::ROUNDS]];
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $times = $input['times'] ?? null;
        if (! is_array($times) || count($times) !== self::ROUNDS) {
            throw new ArcadeRejected('Expected '.self::ROUNDS.' reaction times.');
        }
        $clean = [];
        foreach ($times as $t) {
            $v = filter_var($t, FILTER_VALIDATE_INT);
            if ($v === false || $v < self::MIN_MS || $v > self::MAX_MS) {
                throw new ArcadeRejected('A reaction time outside the human range ('.self::MIN_MS.'–'.self::MAX_MS.' ms) was rejected.');
            }
            $clean[] = $v;
        }
        $this->assertMinElapsed($session, array_sum($session->payload['delays']) + array_sum($clean) - 300);

        $avg = (int) round(array_sum($clean) / count($clean));
        $best = min($clean);
        $points = $avg < 250 ? 10 : ($avg < 350 ? 7 : 4);
        $rating = $avg < 220 ? 'Lightning fast' : ($avg < 280 ? 'Sharp' : ($avg < 380 ? 'Solid' : 'Warming up'));

        return new Outcome(max(0, 1000 - $avg), $points, true, [
            'headline' => $avg.' ms average · '.$rating,
            'stats' => [
                ['label' => 'Average', 'value' => $avg.' ms'],
                ['label' => 'Best', 'value' => $best.' ms'],
                ['label' => 'Rounds', 'value' => (string) self::ROUNDS],
            ],
        ]);
    }
}
