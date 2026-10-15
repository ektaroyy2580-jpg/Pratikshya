<?php

namespace App\Services\Arcade\Games;

use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Arcade\BaseGame;
use App\Services\Arcade\Outcome;

/** Word Challenge — unscramble Nepal-themed English words. The word list stays on the server. */
class WordGame extends BaseGame
{
    private const ROUNDS = 6;

    public function key(): string
    {
        return 'word';
    }

    private function bank(): array
    {
        return [
            ['MOMO', 'Steamed dumplings loved across Nepal', 'Momos arrived with Newar traders from Tibet and are now Kathmandu\'s favourite snack.'],
            ['YAK', 'Shaggy high-altitude animal', 'Yaks carry loads for trekkers above 3,000 m and give milk for chhurpi cheese.'],
            ['SHERPA', 'Mountain people famous for climbing', 'Sherpas live mainly in Solukhumbu; many are expert Himalayan guides.'],
            ['TEMPLE', 'Place of worship', 'Kathmandu Valley has hundreds of temples, from Pashupatinath to Changu Narayan.'],
            ['PAGODA', 'Multi-roofed tower style', 'The Nepali architect Araniko is credited with taking the pagoda style to China.'],
            ['POKHARA', 'Lake city below the Annapurnas', 'Pokhara is the starting point for many Annapurna treks.'],
            ['LUMBINI', 'Birthplace of the Buddha', 'Lumbini is a UNESCO World Heritage Site in Rupandehi district.'],
            ['EVEREST', 'Highest peak on Earth', 'Its Nepali name is Sagarmatha; height 8,848.86 m.'],
            ['RHINO', 'One-horned giant of Chitwan', 'Nepal\'s rhino population has grown thanks to strong conservation.'],
            ['DASHAIN', 'Fifteen-day autumn festival', 'During Dashain, elders give tika and jamara with blessings.'],
            ['TIHAR', 'Festival of lights', 'Tihar honours crows, dogs, cows and brothers over five days.'],
            ['MANDALA', 'Circular sacred art', 'Mandalas are painted in Thangka art and made with coloured sand.'],
            ['MONSOON', 'Rainy season from June to September', 'The monsoon brings around 80% of Nepal\'s annual rainfall.'],
            ['TREKKING', 'Multi-day walking adventure', 'The Annapurna Circuit and Everest Base Camp are famous treks.'],
            ['CHITWAN', 'Nepal\'s first national park', 'Chitwan was established in 1973 in the southern Terai.'],
            ['DANPHE', 'Colourful national bird', 'The Danphe (Himalayan Monal) shimmers in nine colours.'],
            ['STUPA', 'Dome-shaped Buddhist shrine', 'Boudhanath is one of the largest stupas in the world.'],
            ['GLACIER', 'River of slow-moving ice', 'Khumbu Glacier is one of the highest glaciers on Earth.'],
            ['HIMALAYA', 'The great mountain range', 'Eight of the world\'s fourteen 8,000 m peaks are in Nepal.'],
            ['TERAI', 'Southern lowland plains', 'The Terai is Nepal\'s farming belt and home to national parks.'],
            ['PRAYER', '___ flags flutter on mountain passes', 'Prayer flags carry blessings on the wind in five colours.'],
            ['BAZAAR', 'Busy market street', 'Asan bazaar in Kathmandu has traded spices and goods for centuries.'],
            ['VALLEY', 'Low land between hills', 'Kathmandu Valley has seven UNESCO monument zones.'],
            ['ILAM', 'Eastern hill district famous for tea', 'Ilam tea gardens supply tea that is exported around the world.'],
        ];
    }

    public function start(string $variant): array
    {
        $payload = ['words' => [], 'answers' => []];
        $client = ['seconds' => 45, 'words' => []];
        foreach ($this->pick($this->bank(), self::ROUNDS) as $i => [$word, $hint, $fact]) {
            $letters = str_split($word);
            do {
                $scrambled = $this->shuffle($letters);
            } while (implode('', $scrambled) === $word);
            $payload['words'][] = ['word' => $word, 'fact' => $fact];
            $client['words'][] = ['index' => $i, 'letters' => $scrambled, 'length' => strlen($word), 'hint' => $hint];
        }

        return ['payload' => $payload, 'client' => $client];
    }

    public function interact(ArcadeSession $session, array $input): array
    {
        $p = $session->payload;
        $index = filter_var($input['index'] ?? null, FILTER_VALIDATE_INT);
        if ($index === false || ! isset($p['words'][$index])) {
            throw new ArcadeRejected('Unknown word.', false);
        }
        if (array_key_exists($index, $p['answers'] ?? []) || $index !== count($p['answers'] ?? [])) {
            throw new ArcadeRejected('That word is already done.', false, 409);
        }
        $guess = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($input['guess'] ?? '')));
        $word = $p['words'][$index]['word'];
        $correct = $guess !== '' && $guess === $word;
        $p['answers'][$index] = ['correct' => $correct, 'at' => $session->elapsedMs()];

        return [$p, [
            'correct' => $correct, 'solution' => $word, 'explain' => $p['words'][$index]['fact'],
            'score' => $this->score($p)[0], 'answered' => count($p['answers']), 'total' => count($p['words']),
        ]];
    }

    private function score(array $p): array
    {
        $score = $correct = 0;
        foreach ($p['answers'] ?? [] as $i => $a) {
            if ($a['correct']) {
                $correct++;
                $score += 50 + 15 * strlen($p['words'][$i]['word']);
            }
        }

        return [$score, $correct];
    }

    public function finish(ArcadeSession $session, array $input): Outcome
    {
        $p = $session->payload;
        $total = count($p['words']);
        if (count($p['answers'] ?? []) < $total) {
            throw new ArcadeRejected('Finish every word first.', false);
        }
        $this->assertMinElapsed($session, $total * 1000);
        [$score, $correct] = $this->score($p);

        return new Outcome($score, $correct * 3 + ($correct === $total ? 2 : 0), true, [
            'headline' => $correct.' of '.$total.' words solved',
            'stats' => [
                ['label' => 'Solved', 'value' => $correct.'/'.$total],
                ['label' => 'Score', 'value' => (string) $score],
            ],
        ]);
    }
}
