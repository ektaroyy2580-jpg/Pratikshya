<?php

namespace App\Services\Arcade\Games;

use App\Services\Arcade\AiQuestionSource;
use App\Services\Arcade\QuestionGame;

/** Logic Puzzles — sequences, patterns and short reasoning (24 items). */
class LogicGame extends QuestionGame
{
    protected int $secondsPerQuestion = 40;

    public function key(): string
    {
        return 'logic';
    }

    protected function extraQuestions(): array
    {
        return app(AiQuestionSource::class)->questions('logic', 'number sequences and simple logic puzzles with one clear answer');
    }

    protected function bank(): array
    {
        $q = fn ($q, $options, $answer, $explain) => compact('q', 'options', 'answer', 'explain');

        return [
            $q('2, 4, 8, 16, ?', ['24', '30', '32', '64'], 2, 'Each number doubles: 16 × 2 = 32.'),
            $q('1, 1, 2, 3, 5, 8, ?', ['11', '13', '15', '21'], 1, 'Fibonacci: each term is the sum of the two before it (5 + 8 = 13).'),
            $q('3, 6, 11, 18, 27, ?', ['36', '38', '39', '40'], 1, 'The gaps grow by odd numbers: +3, +5, +7, +9, then +11 → 38.'),
            $q('1, 4, 9, 16, 25, ?', ['30', '35', '36', '49'], 2, 'Square numbers: 6² = 36.'),
            $q('100, 95, 85, 70, ?', ['45', '50', '55', '60'], 1, 'Subtract 5, 10, 15, then 20: 70 − 20 = 50.'),
            $q('A, C, F, J, ?', ['M', 'N', 'O', 'P'], 2, 'Letter positions 1, 3, 6, 10 grow by +2, +3, +4, then +5 → 15 = O.'),
            $q('2, 6, 12, 20, 30, ?', ['40', '42', '44', '36'], 1, 'n × (n+1): 6 × 7 = 42.'),
            $q('1, 8, 27, 64, ?', ['100', '125', '128', '81'], 1, 'Cube numbers: 5³ = 125.'),
            $q('81, 27, 9, 3, ?', ['0', '1', '1.5', '2'], 1, 'Divide by 3 each time: 3 ÷ 3 = 1.'),
            $q('Z, X, V, T, ?', ['S', 'R', 'Q', 'P'], 1, 'Go backwards skipping one letter each time: T → R.'),
            $q('2, 3, 5, 7, 11, 13, ?', ['15', '16', '17', '19'], 2, 'Prime numbers: the next prime after 13 is 17.'),
            $q('1, 2, 6, 24, 120, ?', ['240', '600', '720', '840'], 2, 'Multiply by 2, 3, 4, 5, then 6: 120 × 6 = 720 (factorials).'),
            $q('Triangle, Square, Circle, Triangle, Square, Circle, Triangle, ?', ['Triangle', 'Square', 'Circle', 'Star'], 1, 'The pattern repeats every three shapes, so Square follows Triangle.'),
            $q('All Bloops are Razzies. All Razzies are Lazzies. Are all Bloops definitely Lazzies?', ['Yes', 'No', 'Only some', 'Cannot tell'], 0, 'If every Bloop is a Razzie and every Razzie is a Lazzie, every Bloop must be a Lazzie.'),
            $q('4, 9, 19, 39, ?', ['59', '69', '78', '79'], 3, 'Double and add one: 39 × 2 + 1 = 79.'),
            $q('10, 13, 12, 15, 14, ?', ['13', '16', '17', '18'], 2, 'Alternate +3 and −1: 14 + 3 = 17.'),
            $q('Which number is the odd one out? 3, 5, 7, 9, 11', ['3', '7', '9', '11'], 2, '9 is the only one that is not a prime number (9 = 3 × 3).'),
            $q('What is the angle between the hour and minute hands at exactly 3:00?', ['45°', '60°', '90°', '120°'], 2, 'Each hour mark is 30°, and 3 hours apart = 90°.'),
            $q('If today is Monday, what day will it be 10 days from now?', ['Wednesday', 'Thursday', 'Friday', 'Sunday'], 1, '10 days = 1 week + 3 days. Monday + 3 days = Thursday.'),
            $q('1, 3, 7, 15, 31, ?', ['47', '62', '63', '64'], 2, 'Double and add one: 31 × 2 + 1 = 63.'),
            $q('B, E, H, K, ?', ['L', 'M', 'N', 'O'], 2, 'Skip two letters each time (+3): K → N.'),
            $q('Ram is older than Sita. Sita is older than Hari. Who is the youngest?', ['Ram', 'Sita', 'Hari', 'Cannot tell'], 2, 'Ram > Sita > Hari, so Hari is the youngest.'),
            $q('6, 11, 21, 36, 56, ?', ['76', '81', '86', '91'], 1, 'The gaps grow by 5: +5, +10, +15, +20, then +25 → 81.'),
            $q('A tea stall sells 3 cups for Rs 60. How much do 7 cups cost?', ['Rs 120', 'Rs 140', 'Rs 150', 'Rs 210'], 1, 'One cup costs Rs 20, so 7 cups cost Rs 140.'),
        ];
    }
}
