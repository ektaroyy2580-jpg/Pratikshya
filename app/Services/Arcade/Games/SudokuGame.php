<?php

namespace App\Services\Arcade\Games;

use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Arcade\BaseGame;
use App\Services\Arcade\Outcome;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Sudoku — seeded generator producing puzzles with a unique solution.
 * The submitted grid is checked server-side: givens unchanged, every row/column/box 1–9.
 */
class SudokuGame extends BaseGame
{
    private const GIVENS = ['easy' => 40, 'medium' => 32];

    public function key(): string
    {
        return 'sudoku';
    }

    public function variants(): array
    {
        return ['easy', 'medium'];
    }

    public function start(string $variant): array
    {
        $seed = random_int(1, PHP_INT_MAX >> 1);
        [$puzzle, $solution] = self::generate($seed, self::GIVENS[$variant] ?? 40);

        return [
            'payload' => ['seed' => $seed, 'puzzle' => $puzzle, 'solution' => $solution],
            'client' => ['puzzle' => $puzzle, 'difficulty' => $variant],
        ];
    }

    /** @return array{0: int[], 1: int[]} puzzle (0 = blank) and solution, 81 cells each */
    public static function generate(int $seed, int $givens): array
    {
        $r = new Randomizer(new Mt19937($seed));

        // Valid base grid via the canonical pattern, then shuffle bands/rows/stacks/cols/digits.
        $rows = [];
        foreach ($r->shuffleArray([0, 1, 2]) as $band) {
            foreach ($r->shuffleArray([0, 1, 2]) as $row) {
                $rows[] = $band * 3 + $row;
            }
        }
        $cols = [];
        foreach ($r->shuffleArray([0, 1, 2]) as $stack) {
            foreach ($r->shuffleArray([0, 1, 2]) as $col) {
                $cols[] = $stack * 3 + $col;
            }
        }
        $digits = $r->shuffleArray(range(1, 9));
        $solution = [];
        foreach ($rows as $rr) {
            foreach ($cols as $cc) {
                $solution[] = $digits[(3 * ($rr % 3) + intdiv($rr, 3) + $cc) % 9];
            }
        }

        // Remove cells in symmetric pairs while the solution stays unique.
        $puzzle = $solution;
        $filled = 81;
        foreach ($r->shuffleArray(range(0, 40)) as $i) {
            if ($filled <= $givens) {
                break;
            }
            $j = 80 - $i;
            $backup = [$puzzle[$i], $puzzle[$j]];
            $puzzle[$i] = $puzzle[$j] = 0;
            if (self::countSolutions($puzzle, 2) !== 1) {
                [$puzzle[$i], $puzzle[$j]] = $backup;

                continue;
            }
            $filled -= $i === $j ? 1 : 2;
        }

        return [$puzzle, $solution];
    }

    /** Count solutions up to $limit using bitmask backtracking with the most-constrained cell first. */
    public static function countSolutions(array $g, int $limit = 2): int
    {
        $rowM = $colM = $boxM = array_fill(0, 9, 0);
        foreach ($g as $i => $v) {
            if ($v) {
                $bit = 1 << $v;
                $r = intdiv($i, 9);
                $c = $i % 9;
                $b = intdiv($r, 3) * 3 + intdiv($c, 3);
                if (($rowM[$r] | $colM[$c] | $boxM[$b]) & $bit) {
                    return 0;
                }
                $rowM[$r] |= $bit;
                $colM[$c] |= $bit;
                $boxM[$b] |= $bit;
            }
        }
        $count = 0;
        $solve = function () use (&$solve, &$g, &$rowM, &$colM, &$boxM, &$count, $limit) {
            $best = -1;
            $bestMask = 0;
            $bestN = 10;
            for ($i = 0; $i < 81; $i++) {
                if ($g[$i]) {
                    continue;
                }
                $r = intdiv($i, 9);
                $c = $i % 9;
                $b = intdiv($r, 3) * 3 + intdiv($c, 3);
                $mask = ~($rowM[$r] | $colM[$c] | $boxM[$b]) & 0x3FE;
                $n = substr_count(decbin($mask), '1');
                if ($n < $bestN) {
                    [$best, $bestMask, $bestN] = [$i, $mask, $n];
                    if ($n <= 1) {
                        break;
                    }
                }
            }
            if ($best === -1) {
                $count++;

                return;
            }
            $r = intdiv($best, 9);
            $c = $best % 9;
            $b = intdiv($r, 3) * 3 + intdiv($c, 3);
            for ($v = 1; $v <= 9 && $count < $limit; $v++) {
                $bit = 1 << $v;
                if (! ($bestMask & $bit)) {
                    continue;
                }
                $g[$best] = $v;
                $rowM[$r] |= $bit;
                $colM[$c] |= $bit;
                $boxM[$b] |= $bit;
                $solve();
                $rowM[$r] &= ~$bit;
                $colM[$c] &= ~$bit;
                $boxM[$b] &= ~$bit;
                $g[$best] = 0;
            }
        };
        $solve();

        return $count;
    }

    /** True if $grid is a complete valid sudoku that keeps every given of $puzzle. */
    public static function isValidSolution(array $puzzle, array $grid): bool
    {
        if (count($grid) !== 81 || ! array_is_list($grid)) {
            return false;
        }
        foreach ($grid as $i => $v) {
            if (! is_int($v) || $v < 1 || $v > 9 || ($puzzle[$i] && $puzzle[$i] !== $v)) {
                return false;
            }
        }
        for ($k = 0; $k < 9; $k++) {
            $row = $col = $box = [];
            for ($m = 0; $m < 9; $m++) {
                $row[] = $grid[$k * 9 + $m];
                $col[] = $grid[$m * 9 + $k];
                $box[] = $grid[(intdiv($k, 3) * 3 + intdiv($m, 3)) * 9 + ($k % 3) * 3 + $m % 3];
            }
            foreach ([$row, $col, $box] as $set) {
                if (count(array_unique($set)) !== 9) {
                    return false;
                }
            }
        }

        return true;
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $grid = $input['grid'] ?? null;
        $grid = is_array($grid) ? array_map(fn ($v) => filter_var($v, FILTER_VALIDATE_INT) === false ? 0 : (int) $v, array_values($grid)) : [];
        if (! self::isValidSolution($session->payload['puzzle'], $grid)) {
            throw new ArcadeRejected('The grid is not a valid solution yet. Keep going!', false);
        }
        $this->assertMinElapsed($session, 30_000);

        $seconds = intdiv($session->elapsedMs(), 1000);
        $medium = $session->variant === 'medium';
        $score = (int) round(max(200, 3000 - $seconds) * ($medium ? 1.5 : 1));

        return new Outcome($score, $medium ? 25 : 15, true, [
            'headline' => ucfirst((string) $session->variant).' puzzle solved in '.gmdate($seconds >= 3600 ? 'G:i:s' : 'i:s', $seconds),
            'stats' => [
                ['label' => 'Time', 'value' => gmdate('i:s', min($seconds, 3599))],
                ['label' => 'Difficulty', 'value' => ucfirst((string) $session->variant)],
                ['label' => 'Score', 'value' => (string) $score],
            ],
        ]);
    }
}
