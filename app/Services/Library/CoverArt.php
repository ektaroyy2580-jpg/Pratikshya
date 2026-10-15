<?php

namespace App\Services\Library;

/**
 * Generated book covers: an inline SVG built only from whitelisted palettes and motifs
 * (no user text, no external images), so it is safe to print unescaped.
 */
class CoverArt
{
    /** [background top, background bottom, accent, soft] */
    public const PALETTES = [
        'indigo' => ['#2f3fbf', '#1a2370', '#ffd27a', '#8fa2ff'],
        'violet' => ['#7046e8', '#3b1f9e', '#ffc7e6', '#b9a3ff'],
        'teal' => ['#0f8f8a', '#0a4f5c', '#ffe08a', '#7fe0d2'],
        'amber' => ['#f2a33a', '#b8560e', '#fff3c4', '#ffd08a'],
        'rose' => ['#e2557a', '#8f1f4a', '#ffe3ec', '#ff9fb9'],
        'forest' => ['#2f8a4e', '#164a2c', '#f6e7a1', '#8fd6a5'],
        'slate' => ['#4a5578', '#1c2238', '#c8d2f0', '#8a96bd'],
        'sunset' => ['#ff8a5c', '#7046e8', '#fff1c9', '#ffc29e'],
        'ocean' => ['#2f8fe0', '#16357a', '#d9f3ff', '#8ccaf7'],
        'ink' => ['#2b2b33', '#0d0d12', '#f2f2f2', '#8b8b96'],
    ];

    public const MOTIFS = ['mountain', 'moon', 'waves', 'stupa', 'book', 'leaf', 'star', 'grid', 'circuit', 'sun', 'lantern', 'flags', 'magnifier', 'heart', 'cloud', 'bus', 'cup', 'panels'];

    public static function svg(?array $cover, string $seed = ''): string
    {
        $palette = self::PALETTES[$cover['palette'] ?? ''] ?? self::PALETTES['indigo'];
        $motif = in_array($cover['motif'] ?? '', self::MOTIFS, true) ? $cover['motif'] : 'book';
        [$a, $b, $acc, $soft] = $palette;
        $id = 'g'.substr(md5($seed.$motif), 0, 8);

        $art = match ($motif) {
            'mountain' => "<circle cx='150' cy='150' r='22' fill='$acc' opacity='.9'/><path d='M0 300 L0 230 L55 160 L85 195 L120 140 L200 235 L200 300Z' fill='$soft' opacity='.45'/><path d='M0 300 L0 255 L70 190 L110 230 L150 200 L200 250 L200 300Z' fill='$b' opacity='.85'/><path d='M120 140 L106 158 L118 154 L126 162 L133 150Z' fill='#fff' opacity='.9'/>",
            'moon' => "<circle cx='130' cy='160' r='40' fill='$acc'/><circle cx='148' cy='150' r='36' fill='$b' opacity='.95'/><g fill='#fff' opacity='.8'><circle cx='40' cy='140' r='1.6'/><circle cx='70' cy='200' r='1.2'/><circle cx='170' cy='230' r='1.4'/><circle cx='30' cy='250' r='1'/><circle cx='95' cy='120' r='1'/></g>",
            'waves' => "<g fill='none' stroke='$soft' stroke-width='6' opacity='.7'><path d='M-10 200 Q 25 180 60 200 T 130 200 T 210 200'/><path d='M-10 230 Q 25 210 60 230 T 130 230 T 210 230' stroke='$acc'/><path d='M-10 260 Q 25 240 60 260 T 130 260 T 210 260'/></g>",
            'stupa' => "<circle cx='100' cy='150' r='28' fill='$acc' opacity='.25'/><path d='M50 270 h100 v-10 h-100z' fill='$soft' opacity='.7'/><path d='M60 260 Q100 175 140 260Z' fill='#fff' opacity='.9'/><rect x='88' y='196' width='24' height='16' fill='$acc'/><circle cx='95' cy='204' r='2.6' fill='$b'/><circle cx='105' cy='204' r='2.6' fill='$b'/><path d='M92 196 L100 150 L108 196Z' fill='$acc'/><path d='M100 150 L100 136' stroke='$acc' stroke-width='3'/>",
            'book' => "<g transform='translate(100 205)'><path d='M-60 -30 Q -30 -42 0 -30 L0 40 Q -30 28 -60 40Z' fill='#fff' opacity='.92'/><path d='M60 -30 Q 30 -42 0 -30 L0 40 Q 30 28 60 40Z' fill='#fff' opacity='.8'/><g stroke='$soft' stroke-width='2'><path d='M-50 -18 Q -28 -26 -8 -18'/><path d='M-50 -6 Q -28 -14 -8 -6'/><path d='M8 -18 Q 28 -26 50 -18'/><path d='M8 -6 Q 28 -14 50 -6'/></g></g>",
            'leaf' => "<g transform='translate(100 205) rotate(-25)'><path d='M0 -60 Q 45 -10 0 60 Q -45 -10 0 -60Z' fill='$soft' opacity='.85'/><path d='M0 -55 L0 58' stroke='$b' stroke-width='3'/><path d='M0 -20 L18 -32 M0 0 L22 -10 M0 20 L18 10 M0 -10 L-18 -22 M0 10 L-20 0' stroke='$b' stroke-width='2'/></g>",
            'star' => "<g fill='$acc'><path d='M100 140 L111 175 L148 175 L118 196 L129 232 L100 210 L71 232 L82 196 L52 175 L89 175Z'/></g><g fill='#fff' opacity='.7'><circle cx='40' cy='250' r='2'/><circle cx='160' cy='125' r='2'/><circle cx='170' cy='255' r='1.5'/></g>",
            'grid' => "<g stroke='$soft' stroke-width='1.5' opacity='.5'>".implode('', array_map(fn ($i) => "<path d='M{$i} 120 V300'/><path d='M0 ".(120 + $i)." H200'/>", range(0, 200, 25)))."</g><rect x='75' y='170' width='50' height='50' rx='10' fill='$acc'/>",
            'circuit' => "<g stroke='$soft' stroke-width='3' fill='none' opacity='.85'><path d='M20 280 V230 H70 V180'/><path d='M180 280 V240 H130 V170'/><path d='M100 300 V200'/></g><g fill='$acc'><circle cx='70' cy='180' r='7'/><circle cx='130' cy='170' r='7'/><rect x='80' y='185' width='40' height='30' rx='5'/></g>",
            'sun' => "<circle cx='100' cy='205' r='38' fill='$acc'/><g stroke='$acc' stroke-width='5' stroke-linecap='round'>".implode('', array_map(function ($deg) { $r = deg2rad($deg); $x1 = 100 + cos($r) * 50; $y1 = 205 + sin($r) * 50; $x2 = 100 + cos($r) * 64; $y2 = 205 + sin($r) * 64;

                return sprintf("<path d='M%.1f %.1f L%.1f %.1f'/>", $x1, $y1, $x2, $y2); }, range(0, 330, 30)))."</g>",
            'lantern' => "<path d='M100 120 V150' stroke='$soft' stroke-width='2'/><rect x='88' y='148' width='24' height='8' rx='2' fill='$b'/><ellipse cx='100' cy='190' rx='34' ry='36' fill='$acc'/><g stroke='$b' stroke-width='2' fill='none' opacity='.5'><ellipse cx='100' cy='190' rx='18' ry='36'/><path d='M66 190 H134'/></g><rect x='90' y='224' width='20' height='8' rx='2' fill='$b'/><path d='M100 232 V255' stroke='$acc' stroke-width='3'/>",
            'flags' => "<path d='M-5 150 Q 100 200 205 140' stroke='#fff' stroke-width='1.5' fill='none' opacity='.8'/>".implode('', array_map(function ($i) use ($acc, $soft) {
                $x = 8 + $i * 24;
                $y = 150 + sin($i / 7 * M_PI) * 22 - $i * 1.2;
                $colors = ['#3b82f6', '#ffffff', '#ef4444', '#22c55e', '#facc15'];
                $c = $colors[$i % 5];

                return sprintf("<path d='M%.1f %.1f h18 l-2 22 h-14z' fill='%s' opacity='.92'/>", $x, $y, $c);
            }, range(0, 7)))."<path d='M0 300 L0 260 L60 215 L110 250 L160 220 L200 245 L200 300Z' fill='$b' opacity='.7'/>",
            'magnifier' => "<circle cx='92' cy='190' r='36' fill='none' stroke='$acc' stroke-width='9'/><circle cx='92' cy='190' r='26' fill='#fff' opacity='.15'/><path d='M118 216 L150 248' stroke='$acc' stroke-width='12' stroke-linecap='round'/>",
            'heart' => "<path d='M100 250 C 40 210, 50 150, 100 175 C 150 150, 160 210, 100 250Z' fill='$acc'/><path d='M140 150 C 125 140, 128 122, 140 130 C 152 122, 155 140, 140 150Z' fill='$soft' opacity='.8'/>",
            'cloud' => "<g fill='#fff' opacity='.9'><circle cx='80' cy='200' r='26'/><circle cx='110' cy='190' r='32'/><circle cx='138' cy='206' r='20'/><rect x='60' y='200' width='95' height='26' rx='13'/></g><g fill='$soft' opacity='.8'><path d='M80 240 l-6 14 M100 240 l-6 14 M120 240 l-6 14' stroke='$soft' stroke-width='3'/></g>",
            'bus' => "<rect x='40' y='175' width='120' height='62' rx='12' fill='$acc'/><rect x='50' y='185' width='28' height='20' rx='4' fill='$b' opacity='.7'/><rect x='84' y='185' width='28' height='20' rx='4' fill='$b' opacity='.7'/><rect x='118' y='185' width='32' height='20' rx='4' fill='$b' opacity='.7'/><circle cx='70' cy='240' r='10' fill='$b'/><circle cx='130' cy='240' r='10' fill='$b'/><circle cx='70' cy='240' r='4' fill='$soft'/><circle cx='130' cy='240' r='4' fill='$soft'/><path d='M0 252 H200' stroke='$soft' stroke-width='2' opacity='.6'/>",
            'cup' => "<path d='M65 180 h70 l-8 60 q-2 10 -12 10 h-30 q-10 0 -12 -10z' fill='#fff' opacity='.92'/><path d='M135 192 q22 0 18 20 q-3 14 -22 14' fill='none' stroke='#fff' stroke-width='6' opacity='.92'/><g stroke='$acc' stroke-width='4' fill='none' stroke-linecap='round'><path d='M85 168 q-8 -12 0 -24'/><path d='M100 165 q-8 -12 0 -24'/><path d='M115 168 q-8 -12 0 -24'/></g>",
            'panels' => "<g stroke='#fff' stroke-width='3' fill='$soft' fill-opacity='.25'><rect x='22' y='140' width='72' height='60' rx='4'/><rect x='104' y='140' width='74' height='60' rx='4'/><rect x='22' y='210' width='156' height='62' rx='4'/></g><path d='M125 160 h40 a8 8 0 0 1 8 8 v12 a8 8 0 0 1 -8 8 h-28 l-8 8 v-8 h-4 a8 8 0 0 1 -8 -8 v-12 a8 8 0 0 1 8 -8z' fill='#fff'/><circle cx='58' cy='170' r='14' fill='$acc'/>",
        };

        return "<svg viewBox='0 0 200 300' preserveAspectRatio='xMidYMid slice' xmlns='http://www.w3.org/2000/svg' aria-hidden='true' focusable='false'>"
            ."<defs><linearGradient id='$id' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='$a'/><stop offset='1' stop-color='$b'/></linearGradient></defs>"
            ."<rect width='200' height='300' fill='url(#$id)'/>"
            ."<path d='M0 0 H200 V8 H0Z' fill='$acc' opacity='.85'/>"
            .$art
            .'</svg>';
    }
}
