<?php

namespace App\Services\Arcade\Games;

use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Arcade\BaseGame;
use App\Services\Arcade\Outcome;

/**
 * UNO — server-authoritative: the deck, every hand and every rule live on the server.
 * The browser only sends intents (play card i / draw / pass) and receives its own hand,
 * opponents' card counts and an event list to animate. So a "win" can never be forged.
 *
 * Cards: 'R5' (colour + digit), 'GS' skip, 'BR' reverse, 'YD' draw two, 'W' wild, 'W4' wild draw four.
 * House simplifications (documented): the first discard is always a number card; no stacking of
 * draw cards; the Wild Draw Four challenge is replaced by enforcing its legality rule.
 */
class UnoGame extends BaseGame
{
    public const COLORS = ['R', 'G', 'B', 'Y'];
    private const NAMES = ['You', 'Aarav', 'Sita', 'Bikash'];
    private const MAX_TURNS = 600;

    private array $s = [];
    private array $events = [];

    public function key(): string
    {
        return 'uno';
    }

    public function variants(): array
    {
        return ['1', '2', '3'];   // number of computer opponents
    }

    public static function freshDeck(): array
    {
        $deck = [];
        foreach (self::COLORS as $c) {
            $deck[] = $c.'0';
            foreach (array_merge(range(1, 9), ['S', 'R', 'D']) as $v) {
                $deck[] = $c.$v;
                $deck[] = $c.$v;
            }
        }
        for ($i = 0; $i < 4; $i++) {
            $deck[] = 'W';
            $deck[] = 'W4';
        }

        return $deck; // 108 cards
    }

    public function start(string $variant): array
    {
        $players = 1 + (int) $variant;
        $this->s = [
            'deck' => $this->shuffle(self::freshDeck()), 'discard' => [], 'hands' => array_fill(0, $players, []),
            'names' => array_slice(self::NAMES, 0, $players), 'turn' => 0, 'dir' => 1, 'color' => null,
            'drawn' => null, 'winner' => null, 'turns' => 0, 'human_moves' => 0,
        ];
        for ($k = 0; $k < 7; $k++) {
            for ($p = 0; $p < $players; $p++) {
                $this->s['hands'][$p][] = array_pop($this->s['deck']);
            }
        }
        // First discard must be a number card; anything else goes back under the deck.
        while (! ctype_digit(substr(end($this->s['deck']), 1))) {
            array_unshift($this->s['deck'], array_pop($this->s['deck']));
        }
        $top = array_pop($this->s['deck']);
        $this->s['discard'][] = $top;
        $this->s['color'] = $top[0];

        return ['payload' => $this->s, 'client' => $this->view()];
    }

    // ---------------------------------------------------------------- rules

    public static function isWild(string $card): bool
    {
        return $card[0] === 'W';
    }

    public static function value(string $card): string
    {
        return self::isWild($card) ? $card : substr($card, 1);
    }

    public static function points(string $card): int
    {
        if (self::isWild($card)) {
            return 50;
        }
        $v = self::value($card);

        return ctype_digit($v) ? (int) $v : 20;
    }

    private function top(): string
    {
        return end($this->s['discard']);
    }

    private function canPlay(int $p, string $card): bool
    {
        if ($card === 'W') {
            return true;
        }
        if ($card === 'W4') {
            // Legal only when the player holds no card of the current colour.
            foreach ($this->s['hands'][$p] as $c) {
                if (! self::isWild($c) && $c[0] === $this->s['color']) {
                    return false;
                }
            }

            return true;
        }
        $top = $this->top();

        return $card[0] === $this->s['color'] || (! self::isWild($top) && self::value($card) === self::value($top));
    }

    private function count(): int
    {
        return count($this->s['hands']);
    }

    private function next(int $steps = 1): int
    {
        $n = $this->count();

        return ((($this->s['turn'] + $this->s['dir'] * $steps) % $n) + $n) % $n;
    }

    private function drawCards(int $p, int $n): array
    {
        $got = [];
        for ($i = 0; $i < $n; $i++) {
            if (! $this->s['deck']) {
                $top = array_pop($this->s['discard']);
                $this->s['deck'] = $this->shuffle($this->s['discard']);
                $this->s['discard'] = [$top];
            }
            if (! $this->s['deck']) {
                break;
            }
            $card = array_pop($this->s['deck']);
            $this->s['hands'][$p][] = $card;
            $got[] = $card;
        }

        return $got;
    }

    private function event(int $p, string $t, array $extra = []): void
    {
        $this->events[] = ['p' => $p, 't' => $t] + $extra;
    }

    private function giveCards(int $p, int $n, string $type): void
    {
        $got = $this->drawCards($p, $n);
        $this->event($p, $type, ['n' => count($got)] + ($p === 0 ? ['cards' => $got] : []));
    }

    private function playCard(int $p, int $idx, ?string $color, bool $uno): void
    {
        $card = $this->s['hands'][$p][$idx];
        array_splice($this->s['hands'][$p], $idx, 1);
        $this->s['discard'][] = $card;
        $this->s['color'] = self::isWild($card) ? $color : $card[0];
        $this->event($p, 'play', ['card' => $card, 'color' => $this->s['color']]);

        $left = count($this->s['hands'][$p]);
        if ($left === 0) {
            $this->s['winner'] = $p;
            $this->event($p, 'win');

            return;
        }
        if ($left === 1) {
            if ($uno) {
                $this->event($p, 'uno');
            } else {
                $this->giveCards($p, 2, 'penalty');   // forgot to call UNO and got caught
            }
        }

        $v = self::value($card);
        $n = $this->count();
        if ($v === 'S') {
            $this->event($this->next(), 'skip');
            $this->s['turn'] = $this->next(2);
        } elseif ($v === 'R') {
            if ($n === 2) {
                $this->event($this->next(), 'skip');
                $this->s['turn'] = $this->next(2);
            } else {
                $this->s['dir'] *= -1;
                $this->event($p, 'reverse');
                $this->s['turn'] = $this->next();
            }
        } elseif ($v === 'D' || $v === 'W4') {
            $victim = $this->next();
            $this->giveCards($victim, $v === 'D' ? 2 : 4, 'draw');
            $this->event($victim, 'skip');
            $this->s['turn'] = $this->next(2);
        } else {
            $this->s['turn'] = $this->next();
        }
    }

    // ---------------------------------------------------------------- CPU

    private function bestColor(int $p): string
    {
        $tally = array_fill_keys(self::COLORS, 0);
        foreach ($this->s['hands'][$p] as $c) {
            if (! self::isWild($c)) {
                $tally[$c[0]]++;
            }
        }
        arsort($tally);
        $top = array_key_first($tally);

        return $tally[$top] > 0 ? $top : self::COLORS[random_int(0, 3)];
    }

    private function cpuTurn(int $p): void
    {
        $choices = [];
        foreach ($this->s['hands'][$p] as $i => $c) {
            if ($this->canPlay($p, $c)) {
                $choices[] = $i;
            }
        }
        if (! $choices) {
            $got = $this->drawCards($p, 1);
            $this->event($p, 'draw', ['n' => count($got)]);
            $i = count($this->s['hands'][$p]) - 1;
            if ($got && $this->canPlay($p, $this->s['hands'][$p][$i])) {
                $choices = [$i];
            } else {
                $this->event($p, 'pass');
                $this->s['turn'] = $this->next();

                return;
            }
        }

        $threat = count($this->s['hands'][$this->next()]) <= 2;
        $hand = $this->s['hands'][$p];
        usort($choices, function ($a, $b) use ($hand, $threat) {
            $rank = function ($c) use ($threat) {
                if ($c === 'W4') {
                    return $threat ? 95 : 5;
                }
                if ($c === 'W') {
                    return 10;
                }
                $v = self::value($c);
                if (! ctype_digit($v)) {
                    return $threat ? 90 : 40;
                }

                return 20 + (int) $v;          // dump high numbers first
            };

            return $rank($hand[$b]) <=> $rank($hand[$a]);
        });
        $idx = $choices[0];
        $color = null;
        if (self::isWild($hand[$idx])) {
            // Pick the colour this CPU holds most of (ignoring the wild it is about to play).
            $rest = $hand;
            array_splice($rest, $idx, 1);
            $this->s['hands'][$p] = $rest;
            $color = $this->bestColor($p);
            $this->s['hands'][$p] = $hand;
        }
        $this->playCard($p, $idx, $color, random_int(1, 100) <= 85);
    }

    private function runCpus(): void
    {
        while ($this->s['winner'] === null && $this->s['turn'] !== 0) {
            $this->cpuTurn($this->s['turn']);
            if (++$this->s['turns'] > self::MAX_TURNS) {
                $this->s['winner'] = -1;   // stalemate safety net
            }
        }
    }

    // ---------------------------------------------------------------- human

    public function interact(ArcadeSession $session, array $input): array
    {
        $this->s = $session->payload;
        $this->events = [];
        if ($this->s['winner'] !== null) {
            throw new ArcadeRejected('This game is over.', false, 409);
        }
        if ($this->s['turn'] !== 0) {
            throw new ArcadeRejected('It is not your turn.', false, 409);
        }

        $action = $input['action'] ?? null;
        if ($action === 'play') {
            $idx = filter_var($input['card'] ?? null, FILTER_VALIDATE_INT);
            $hand = $this->s['hands'][0];
            if ($idx === false || ! isset($hand[$idx])) {
                throw new ArcadeRejected('Unknown card.', false);
            }
            if ($this->s['drawn'] !== null && $idx !== $this->s['drawn']) {
                throw new ArcadeRejected('After drawing you may only play the drawn card, or pass.', false);
            }
            if (! $this->canPlay(0, $hand[$idx])) {
                throw new ArcadeRejected($hand[$idx] === 'W4' ? 'Wild Draw Four is only allowed when you have no card of the current colour.' : 'That card does not match the colour or symbol.', false);
            }
            $color = null;
            if (self::isWild($hand[$idx])) {
                $color = $input['color'] ?? null;
                if (! in_array($color, self::COLORS, true)) {
                    throw new ArcadeRejected('Choose a colour for your wild card.', false);
                }
            }
            $this->s['drawn'] = null;
            $this->s['human_moves']++;
            $this->playCard(0, $idx, $color, filter_var($input['uno'] ?? false, FILTER_VALIDATE_BOOL));
        } elseif ($action === 'draw') {
            if ($this->s['drawn'] !== null) {
                throw new ArcadeRejected('You already drew a card this turn.', false);
            }
            $this->s['human_moves']++;
            $got = $this->drawCards(0, 1);
            $this->event(0, 'draw', ['n' => count($got), 'cards' => $got]);
            $i = count($this->s['hands'][0]) - 1;
            if ($got && $this->canPlay(0, $this->s['hands'][0][$i])) {
                $this->s['drawn'] = $i;    // may play it now, or pass
            } else {
                $this->event(0, 'pass');
                $this->s['turn'] = $this->next();
            }
        } elseif ($action === 'pass') {
            if ($this->s['drawn'] === null) {
                throw new ArcadeRejected('Draw a card before passing.', false);
            }
            $this->s['drawn'] = null;
            $this->event(0, 'pass');
            $this->s['turn'] = $this->next();
        } else {
            throw new ArcadeRejected('Unknown action.', false);
        }

        $this->runCpus();

        return [$this->s, $this->view()];
    }

    private function view(): array
    {
        $hand = [];
        foreach ($this->s['hands'][0] as $i => $c) {
            $ok = $this->s['turn'] === 0 && $this->s['winner'] === null && ($this->s['drawn'] === null || $this->s['drawn'] === $i) && $this->canPlay(0, $c);
            $hand[] = ['card' => $c, 'playable' => $ok];
        }
        $players = [];
        foreach ($this->s['hands'] as $p => $h) {
            $players[] = ['name' => $this->s['names'][$p], 'count' => count($h), 'cpu' => $p !== 0];
        }

        return [
            'hand' => $hand, 'players' => $players, 'top' => $this->top(), 'color' => $this->s['color'],
            'dir' => $this->s['dir'], 'turn' => $this->s['turn'], 'deck' => count($this->s['deck']),
            'drawn' => $this->s['drawn'], 'winner' => $this->s['winner'], 'events' => $this->events,
        ];
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $s = $session->payload;
        if ($s['winner'] === null) {
            if (! filter_var($input['forfeit'] ?? false, FILTER_VALIDATE_BOOL)) {
                throw new ArcadeRejected('The game is still in progress.', false);
            }

            return new Outcome(0, 0, true, ['headline' => 'Game abandoned', 'stats' => [['label' => 'Result', 'value' => 'Forfeit']]]);
        }
        $this->assertMinElapsed($session, 15_000);

        $won = $s['winner'] === 0;
        $left = 0;
        foreach ($s['hands'] as $p => $h) {
            if ($p !== 0) {
                foreach ($h as $c) {
                    $left += self::points($c);
                }
            }
        }
        $winnerName = $s['winner'] >= 0 ? $s['names'][$s['winner']] : 'Nobody';

        return new Outcome($won ? $left : 0, $won ? 20 : 3, true, [
            'headline' => $won ? 'You won UNO!' : $winnerName.' won this round',
            'stats' => [
                ['label' => 'Result', 'value' => $won ? 'Win' : 'Loss'],
                ['label' => 'Opponents', 'value' => (string) (count($s['hands']) - 1)],
                ['label' => 'Card points', 'value' => (string) ($won ? $left : 0)],
            ],
        ]);
    }
}
