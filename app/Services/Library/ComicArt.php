<?php

namespace App\Services\Library;

/**
 * Draws ORIGINAL comic panels as inline SVG from a small JSON spec (scene + figures + props).
 * Speech bubbles and captions are rendered as escaped HTML on top (see views/library/partials/comic-page).
 * Only whitelisted shapes/colours are emitted and every number is cast, so the SVG is safe to print.
 */
class ComicArt
{
    private const H = 300;

    private const GROUND = 252;

    private bool $mono = false;

    private string $uid = '';

    public function panel(array $panel, string $style = 'color', string $uid = 'p'): string
    {
        $this->mono = $style === 'manga';
        $this->uid = preg_replace('/[^a-z0-9]/i', '', $uid);
        $w = ((int) ($panel['span'] ?? 1)) === 2 ? 800 : 400;

        $svg = "<svg viewBox='0 0 $w ".self::H."' xmlns='http://www.w3.org/2000/svg' preserveAspectRatio='xMidYMid slice' aria-hidden='true' focusable='false'>";
        $svg .= $this->defs();
        $svg .= $this->scene((string) ($panel['scene'] ?? 'plain'), $w, (string) ($panel['tone'] ?? ''));
        foreach ((array) ($panel['props'] ?? []) as $prop) {
            $svg .= $this->prop((array) $prop, $w);
        }
        foreach ((array) ($panel['figures'] ?? []) as $fig) {
            $svg .= $this->figure((array) $fig, $w);
        }
        if (! empty($panel['lines'])) {
            $svg .= $this->speedLines($w, (string) $panel['lines']);
        }
        if ($this->mono) {
            $svg .= "<rect width='$w' height='".self::H."' fill='url(#tone{$this->uid})' opacity='.18'/>";
        }

        return $svg.'</svg>';
    }

    /** Colour helper: in manga mode everything maps to greys. */
    private function c(string $hex): string
    {
        if (! $this->mono || ! preg_match('/^#([0-9a-f]{6})$/i', $hex, $m)) {
            return $hex;
        }
        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));
        $l = (int) round(0.3 * $r + 0.59 * $g + 0.11 * $b);
        $l = $l > 200 ? 255 : ($l > 140 ? 215 : ($l > 90 ? 150 : ($l > 50 ? 80 : 25)));

        return sprintf('#%02x%02x%02x', $l, $l, $l);
    }

    private function defs(): string
    {
        $u = $this->uid;

        return "<defs><pattern id='tone$u' width='6' height='6' patternUnits='userSpaceOnUse'><circle cx='3' cy='3' r='1.3' fill='#000'/></pattern>"
            ."<linearGradient id='sky$u' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='".$this->c('#9fd3ff')."'/><stop offset='1' stop-color='".$this->c('#e8f5ff')."'/></linearGradient>"
            ."<linearGradient id='night$u' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='".$this->c('#1b1f4b')."'/><stop offset='1' stop-color='".$this->c('#3d3a7a')."'/></linearGradient></defs>";
    }

    // ------------------------------------------------------------------ scenes

    private function scene(string $scene, int $w, string $tone): string
    {
        $H = self::H;
        $G = self::GROUND;
        $u = $this->uid;
        $c = fn ($h) => $this->c($h);

        return match ($scene) {
            'street' => "<rect width='$w' height='$H' fill='url(#sky$u)'/>".$this->buildings($w)."<rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#c9b79a')}'/><path d='M0 $G H$w' stroke='{$c('#8a7556')}' stroke-width='3'/>",
            'office' => "<rect width='$w' height='$H' fill='{$c('#eef1fb')}'/><rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#c7cde3')}'/>"
                ."<rect x='".($w - 130)."' y='40' width='100' height='70' rx='6' fill='{$c('#b9dcff')}' stroke='{$c('#8a96bd')}' stroke-width='4'/><path d='M".($w - 80)." 40 V110' stroke='{$c('#8a96bd')}' stroke-width='3'/>"
                ."<rect x='30' y='30' width='120' height='46' rx='8' fill='{$c('#1c2238')}'/><text x='90' y='62' text-anchor='middle' font-family='monospace' font-weight='700' font-size='24' fill='{$c('#ffd27a')}'>D-24</text>",
            'counter' => "<rect width='$w' height='$H' fill='{$c('#eef1fb')}'/><rect x='0' y='170' width='$w' height='".($H - 170)."' fill='{$c('#8a6a4a')}'/><rect x='0' y='164' width='$w' height='12' fill='{$c('#6b4f33')}'/>"
                ."<rect x='".($w / 2 - 50)."' y='28' width='100' height='40' rx='8' fill='{$c('#1c2238')}'/><text x='".($w / 2)."' y='56' text-anchor='middle' font-family='monospace' font-weight='700' font-size='20' fill='{$c('#7fe0d2')}'>COUNTER 9</text>",
            'mountain' => "<rect width='$w' height='$H' fill='url(#sky$u)'/><path d='M0 200 L".($w * .2).' 90 L'.($w * .35).' 150 L'.($w * .55)." 60 L".($w * .8).' 160 L'.($w)." 110 V$H H0Z' fill='{$c('#a9b6d6')}'/>"
                ."<path d='M".($w * .55).' 60 L'.($w * .5).' 90 L'.($w * .56).' 84 L'.($w * .6)." 96Z' fill='#fff'/><path d='M".($w * .2).' 90 L'.($w * .16).' 115 L'.($w * .21).' 108 L'.($w * .25)." 118Z' fill='#fff'/>"
                ."<path d='M0 $G Q".($w / 2)." 220 $w $G V$H H0Z' fill='{$c('#7cb07a')}'/>",
            'night' => "<rect width='$w' height='$H' fill='url(#night$u)'/><circle cx='".($w - 70)."' cy='60' r='26' fill='{$c('#fff3c4')}'/>".$this->stars($w)
                ."<rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#2a2850')}'/>",
            'room' => "<rect width='$w' height='$H' fill='{$c('#ffe9cf')}'/><rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#c98d5a')}'/><rect x='".($w - 150)."' y='50' width='90' height='90' rx='6' fill='url(#sky$u)' stroke='{$c('#8a5a33')}' stroke-width='6'/><path d='M".($w - 105)." 50 V140 M".($w - 150)." 95 H".($w - 60)."' stroke='{$c('#8a5a33')}' stroke-width='4'/>",
            'market' => "<rect width='$w' height='$H' fill='url(#sky$u)'/>".$this->stalls($w)."<rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#d8c3a0')}'/>",
            'bus' => "<rect width='$w' height='$H' fill='{$c('#ffd27a')}'/>".implode('', array_map(fn ($x) => "<rect x='$x' y='40' width='".min(110, $w - $x - 20)."' height='80' rx='10' fill='url(#night$u)' stroke='{$c('#b8560e')}' stroke-width='5'/>", range(20, $w - 100, 140)))
                ."<rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#6b6b80')}'/><path d='M0 30 H$w' stroke='{$c('#b8560e')}' stroke-width='6'/>",
            'temple' => "<rect width='$w' height='$H' fill='url(#sky$u)'/>".$this->pagoda($w * .7)."<rect y='$G' width='$w' height='".($H - $G)."' fill='{$c('#b9a184')}'/>",
            'burst' => "<rect width='$w' height='$H' fill='".$c($tone ?: '#ffe08a')."'/>".$this->burst($w),
            default => "<rect width='$w' height='$H' fill='".$c($tone ?: '#f3efff')."'/>",
        };
    }

    private function buildings(int $w): string
    {
        $out = '';
        $cols = ['#d98c6a', '#c7795a', '#e0a07a', '#b8674a'];
        for ($x = -10, $i = 0; $x < $w; $x += 90, $i++) {
            $h = 90 + (($i * 37) % 60);
            $y = self::GROUND - $h;
            $out .= "<rect x='$x' y='$y' width='84' height='$h' fill='".$this->c($cols[$i % 4])."'/>";
            $out .= "<path d='M".($x - 6)." $y h96 l-8 -12 h-80z' fill='".$this->c('#7a4a33')."'/>";
            for ($wy = $y + 16; $wy < self::GROUND - 30; $wy += 34) {
                $out .= "<rect x='".($x + 14)."' y='$wy' width='18' height='20' fill='".$this->c('#5b3a2a')."'/><rect x='".($x + 52)."' y='$wy' width='18' height='20' fill='".$this->c('#5b3a2a')."'/>";
            }
        }

        return $out;
    }

    private function stalls(int $w): string
    {
        $out = '';
        $cols = ['#e2557a', '#2f8fe0', '#2f8a4e', '#f2a33a'];
        for ($x = 10, $i = 0; $x < $w; $x += 130, $i++) {
            $col = $this->c($cols[$i % 4]);
            $out .= "<rect x='".($x + 8)."' y='150' width='104' height='".(self::GROUND - 150)."' fill='".$this->c('#a87b54')."'/>";
            $out .= "<path d='M$x 150 h120 l-10 -36 h-100z' fill='$col'/>";
            $out .= "<rect x='".($x + 16)."' y='190' width='88' height='14' rx='4' fill='".$this->c('#f6e7a1')."'/>";
        }

        return $out;
    }

    private function pagoda(float $cx): string
    {
        $roof = $this->c('#7a3a22');
        $wall = $this->c('#b8674a');
        $G = self::GROUND;
        $out = "<rect x='".($cx - 60)."' y='".($G - 24)."' width='120' height='24' fill='".$this->c('#8a7556')."'/>";
        foreach ([[0, 60, 50], [1, 46, 42], [2, 32, 34]] as [$i, $half, $h]) {
            $base = $G - 24 - $i * 52;
            $out .= "<rect x='".($cx - $half * .7)."' y='".($base - $h)."' width='".($half * 1.4)."' height='$h' fill='$wall'/>";
            $out .= "<path d='M".($cx - $half - 14).' '.($base - $h + 6).' L'.($cx).' '.($base - $h - 22).' L'.($cx + $half + 14).' '.($base - $h + 6)."Z' fill='$roof'/>";
        }
        $out .= "<path d='M$cx ".($G - 24 - 3 * 52 - 4)." v-20' stroke='".$this->c('#f2a33a')."' stroke-width='5'/>";

        return $out;
    }

    private function stars(int $w): string
    {
        $out = '';
        for ($i = 0; $i < 18; $i++) {
            $out .= "<circle cx='".(($i * 97) % $w)."' cy='".(20 + ($i * 53) % 150)."' r='".(1 + $i % 2)."' fill='#fff' opacity='.85'/>";
        }

        return $out;
    }

    private function burst(int $w): string
    {
        $cx = $w / 2;
        $cy = self::H / 2;
        $out = "<g fill='".$this->c('#ffffff')."' opacity='.65'>";
        for ($a = 0; $a < 360; $a += 15) {
            $r1 = deg2rad($a);
            $r2 = deg2rad($a + 6);
            $out .= sprintf("<path d='M%.1f %.1f L%.1f %.1f L%.1f %.1f Z'/>", $cx, $cy, $cx + cos($r1) * 900, $cy + sin($r1) * 900, $cx + cos($r2) * 900, $cy + sin($r2) * 900);
        }

        return $out.'</g>';
    }

    private function speedLines(int $w, string $dir): string
    {
        $out = "<g stroke='".$this->c('#0f1535')."' stroke-width='2' opacity='.55' stroke-linecap='round'>";
        for ($i = 0; $i < 14; $i++) {
            $y = 20 + ($i * 41) % 260;
            $len = 60 + ($i * 29) % 90;
            $x = $dir === 'left' ? ($i * 53) % 120 : $w - ($i * 53) % 120 - $len;
            $out .= "<path d='M$x $y h$len'/>";
        }

        return $out.'</g>';
    }

    // ------------------------------------------------------------------ props

    private function prop(array $p, int $w): string
    {
        $x = (float) ($p['x'] ?? 50) / 100 * $w;
        $y = isset($p['y']) ? (float) $p['y'] / 100 * self::H : self::GROUND;
        $s = max(.3, min(3, (float) ($p['scale'] ?? 1)));
        $c = fn ($h) => $this->c($h);
        $tf = sprintf("transform='translate(%.1f %.1f) scale(%.2f)'", $x, $y, $s);

        $art = match ((string) ($p['kind'] ?? '')) {
            'tea' => "<path d='M-14 -26 h28 l-4 26 h-20z' fill='{$c('#ffffff')}' stroke='{$c('#8a5a33')}' stroke-width='2'/><path d='M-6 -32 q-4 -8 0 -14 M4 -32 q-4 -8 0 -14' stroke='{$c('#9aa3c0')}' stroke-width='2' fill='none'/>",
            'token' => "<rect x='-22' y='-30' width='44' height='30' rx='5' fill='{$c('#ffffff')}' stroke='{$c('#2f5bea')}' stroke-width='3'/><text x='0' y='-10' text-anchor='middle' font-family='monospace' font-weight='700' font-size='13' fill='{$c('#2f5bea')}'>".preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string) ($p['label'] ?? 'A-7')))."</text>",
            'tree' => "<rect x='-6' y='-60' width='12' height='60' fill='{$c('#7a4a33')}'/><circle cx='0' cy='-80' r='34' fill='{$c('#3e9a5a')}'/><circle cx='-22' cy='-62' r='20' fill='{$c('#2f8a4e')}'/><circle cx='22' cy='-64' r='22' fill='{$c('#2f8a4e')}'/>",
            'sun' => "<circle cx='0' cy='0' r='24' fill='{$c('#ffd23f')}'/>",
            'moon' => "<circle cx='0' cy='0' r='20' fill='{$c('#fff3c4')}'/><circle cx='9' cy='-5' r='17' fill='{$c('#1b1f4b')}'/>",
            'clock' => "<circle r='22' fill='{$c('#ffffff')}' stroke='{$c('#1c2238')}' stroke-width='4'/><path d='M0 0 V-14 M0 0 H10' stroke='{$c('#1c2238')}' stroke-width='3' stroke-linecap='round'/>",
            'book' => "<path d='M-20 -14 q10 -6 20 0 v24 q-10 -6 -20 0z' fill='{$c('#2f5bea')}'/><path d='M20 -14 q-10 -6 -20 0 v24 q10 -6 20 0z' fill='{$c('#7046e8')}'/>",
            'bench' => "<rect x='-50' y='-30' width='100' height='10' rx='3' fill='{$c('#8a5a33')}'/><rect x='-44' y='-20' width='6' height='20' fill='{$c('#5b3a2a')}'/><rect x='38' y='-20' width='6' height='20' fill='{$c('#5b3a2a')}'/>",
            'box' => "<rect x='-24' y='-40' width='48' height='40' rx='3' fill='{$c('#c98d5a')}' stroke='{$c('#8a5a33')}' stroke-width='3'/><path d='M-24 -28 H24 M0 -40 V0' stroke='{$c('#8a5a33')}' stroke-width='3'/>",
            'momo' => "<ellipse cx='0' cy='-4' rx='30' ry='6' fill='{$c('#ffffff')}' stroke='{$c('#9aa3c0')}' stroke-width='2'/>".implode('', array_map(fn ($dx) => "<path d='M".($dx - 8)." -6 q8 -18 16 0z' fill='{$c('#fff8ec')}' stroke='{$c('#d9c6a5')}' stroke-width='1.5'/>", [-14, 0, 14])),
            'star' => "<path d='M0 -14 L4 -4 L14 -4 L6 3 L9 13 L0 7 L-9 13 L-6 3 L-14 -4 L-4 -4Z' fill='{$c('#ffd23f')}'/>",
            'heart' => "<path d='M0 10 C -18 -2, -12 -18, 0 -8 C 12 -18, 18 -2, 0 10Z' fill='{$c('#e2557a')}'/>",
            'sweat' => "<path d='M0 -12 q8 12 0 16 q-8 -4 0 -16z' fill='{$c('#8ccaf7')}'/>",
            'flags' => "<path d='M-80 0 Q0 30 80 0' stroke='{$c('#ffffff')}' stroke-width='1.5' fill='none'/>".implode('', array_map(fn ($i) => "<path d='M".(-76 + $i * 20).' '.(4 + (int) (sin($i / 7 * M_PI) * 12))." h14 l-2 18 h-10z' fill='".$c(['#3b82f6', '#ffffff', '#ef4444', '#22c55e', '#facc15'][$i % 5])."'/>", range(0, 7))),
            'scooter' => "<circle cx='-26' cy='-12' r='12' fill='{$c('#1c2238')}'/><circle cx='26' cy='-12' r='12' fill='{$c('#1c2238')}'/><path d='M-30 -22 h50 l10 -30 h8' stroke='{$c('#e2557a')}' stroke-width='10' fill='none' stroke-linecap='round'/>",
            default => '',
        };

        return $art === '' ? '' : "<g $tf>$art</g>";
    }

    // ------------------------------------------------------------------ figures

    private const PEOPLE = [
        // hair, hairStyle, skin, top, bottom, extra
        'kid' => ['#1c1c22', 'short', '#c98d68', '#2f8fe0', '#1c2238', ''],
        'girl' => ['#1c1c22', 'pigtails', '#c98d68', '#e2557a', '#4a5578', ''],
        'grandma' => ['#c8c8d0', 'bun', '#b97f5c', '#7046e8', '#7a3a22', 'glasses shawl'],
        'dai' => ['#1c1c22', 'short', '#b97f5c', '#ffffff', '#4a5578', 'topi'],
        'officer' => ['#1c1c22', 'short', '#c98d68', '#8a7a4a', '#3b3b2a', 'cap badge'],
        'ronin' => ['#111111', 'spiky', '#e9c9a8', '#22222a', '#22222a', 'scarf'],
        'clerk' => ['#3b2a1a', 'long', '#d9a27e', '#2f8a4e', '#1c2238', 'glasses'],
        'stranger' => ['#5b3a2a', 'spiky', '#c98d68', '#e2557a', '#1c2238', 'shades'],
    ];

    private function figure(array $f, int $w): string
    {
        $kind = (string) ($f['who'] ?? 'kid');
        $x = (float) ($f['x'] ?? 50) / 100 * $w;
        $s = max(.4, min(2.5, (float) ($f['scale'] ?? 1)));
        $flip = ! empty($f['flip']) ? -1 : 1;
        $mood = (string) ($f['mood'] ?? 'happy');
        $y = self::GROUND + (float) ($f['dy'] ?? 0);
        $tf = sprintf("transform='translate(%.1f %.1f) scale(%.2f %.2f)'", $x, $y, $s * $flip, $s);

        $art = match (true) {
            $kind === 'momo' => $this->momo($mood),
            $kind === 'yeti' => $this->yeti($mood),
            $kind === 'cat' => $this->cat($mood),
            $kind === 'robot' => $this->robot($mood),
            isset(self::PEOPLE[$kind]) => $this->person(self::PEOPLE[$kind], $mood, in_array($kind, ['kid', 'girl'], true) ? .78 : 1),
            default => '',
        };

        return $art === '' ? '' : "<g $tf>$art</g>";
    }

    private function face(float $cx, float $cy, string $mood, float $r = 1, string $ink = '#1c1c22'): string
    {
        $k = $this->c($ink);
        $ex = 9 * $r;
        $ey = $cy - 2 * $r;
        $eyeR = ($mood === 'surprised' ? 3.6 : 2.6) * $r;
        $o = '';
        if ($mood === 'sleepy') {
            $o .= "<path d='M".($cx - $ex - 4)." $ey h8 M".($cx + $ex - 4)." $ey h8' stroke='$k' stroke-width='2.4' stroke-linecap='round'/>";
        } elseif ($mood === 'happy' || $mood === 'laugh') {
            $o .= "<path d='M".($cx - $ex - 4)." $ey q4 -5 8 0 M".($cx + $ex - 4)." $ey q4 -5 8 0' stroke='$k' stroke-width='2.4' fill='none' stroke-linecap='round'/>";
        } else {
            $o .= "<circle cx='".($cx - $ex)."' cy='$ey' r='$eyeR' fill='$k'/><circle cx='".($cx + $ex)."' cy='$ey' r='$eyeR' fill='$k'/>";
        }
        if ($mood === 'angry' || $mood === 'determined') {
            $o .= "<path d='M".($cx - $ex - 6).' '.($ey - 9)." l10 4 M".($cx + $ex + 6).' '.($ey - 9)." l-10 4' stroke='$k' stroke-width='2.6' stroke-linecap='round'/>";
        }
        if ($mood === 'sad' || $mood === 'worried') {
            $o .= "<path d='M".($cx - $ex - 5).' '.($ey - 6)." l9 -3 M".($cx + $ex + 5).' '.($ey - 6)." l-9 -3' stroke='$k' stroke-width='2.2' stroke-linecap='round'/>";
        }
        $my = $cy + 10 * $r;
        $o .= match ($mood) {
            'happy' => "<path d='M".($cx - 7 * $r)." $my q".(7 * $r).' '.(7 * $r).' '.(14 * $r)." 0' stroke='$k' stroke-width='2.4' fill='none' stroke-linecap='round'/>",
            'laugh' => "<path d='M".($cx - 8 * $r)." $my q".(8 * $r).' '.(12 * $r).' '.(16 * $r)." 0z' fill='".$this->c('#a3153a')."'/>",
            'sad', 'worried' => "<path d='M".($cx - 6 * $r).' '.($my + 4)." q".(6 * $r).' '.(-6 * $r).' '.(12 * $r)." 0' stroke='$k' stroke-width='2.4' fill='none' stroke-linecap='round'/>",
            'surprised' => "<ellipse cx='$cx' cy='".($my + 2)."' rx='".(4 * $r)."' ry='".(5 * $r)."' fill='$k'/>",
            default => "<path d='M".($cx - 6 * $r)." ".($my + 1).' h'.(12 * $r)."' stroke='$k' stroke-width='2.4' stroke-linecap='round'/>",
        };

        return $o;
    }

    private function person(array $p, string $mood, float $k): string
    {
        [$hair, $style, $skin, $top, $bottom, $extra] = $p;
        $c = fn ($h) => $this->c($h);
        $hair = $c($hair);
        $skin = $c($skin);
        $top = $c($top);
        $bottom = $c($bottom);
        $headY = -128 * $k;
        $r = 26;
        $o = "<g transform='scale($k)'>";
        // legs + body + arms
        $ink = "stroke='#1c1c22' stroke-width='2.2'";
        $o .= "<rect x='-16' y='-62' width='13' height='62' rx='5' fill='$bottom' $ink/><rect x='3' y='-62' width='13' height='62' rx='5' fill='$bottom' $ink/>";
        $o .= "<rect x='-24' y='-104' width='48' height='52' rx='16' fill='$top' $ink/>";
        $arm = in_array($mood, ['surprised', 'laugh'], true) ? "<path d='M-22 -96 l-20 -26 M22 -96 l20 -26' stroke='$top' stroke-width='11' stroke-linecap='round'/>"
            : ($mood === 'determined' ? "<path d='M-22 -96 l-16 24 M22 -96 l22 -10' stroke='$top' stroke-width='11' stroke-linecap='round'/>"
            : "<path d='M-22 -96 l-10 34 M22 -96 l10 34' stroke='$top' stroke-width='11' stroke-linecap='round'/>");
        $o .= $arm;
        if (str_contains($extra, 'scarf')) {
            $o .= "<path d='M-20 -104 h40 v10 h-40z M10 -96 l18 30 l-10 4z' fill='".$c('#c0392b')."'/>";
        }
        if (str_contains($extra, 'shawl')) {
            $o .= "<path d='M-26 -104 h52 l-6 30 h-40z' fill='".$c('#e2557a')."' opacity='.9'/>";
        }
        if (str_contains($extra, 'badge')) {
            $o .= "<circle cx='12' cy='-90' r='4' fill='".$c('#ffd23f')."'/>";
        }
        // head
        $hy = -128;
        $o .= "<rect x='-6' y='-110' width='12' height='10' fill='$skin'/>";
        if ($style === 'long') {
            $o .= "<path d='M-28 $hy q0 -34 28 -32 q28 -2 28 32 v40 h-56z' fill='$hair'/>";
        }
        if ($style === 'pigtails') {
            $o .= "<circle cx='-30' cy='".($hy + 4)."' r='10' fill='$hair'/><circle cx='30' cy='".($hy + 4)."' r='10' fill='$hair'/>";
        }
        $o .= "<circle cx='0' cy='$hy' r='$r' fill='$skin' $ink/>";
        $o .= match ($style) {
            'short' => "<path d='M-26 ".($hy - 4)." q2 -26 26 -24 q24 -2 26 24 q-12 -10 -26 -8 q-16 -2 -26 8z' fill='$hair'/>",
            'bun' => "<circle cx='0' cy='".($hy - 30)."' r='11' fill='$hair'/><path d='M-26 ".($hy - 2)." q0 -26 26 -26 q26 0 26 26 q-10 -14 -26 -14 q-16 0 -26 14z' fill='$hair'/>",
            'spiky' => "<path d='M-28 ".($hy - 2)." l4 -22 l8 8 l6 -20 l8 14 l8 -18 l6 16 l10 -10 l2 24 q-14 -10 -26 -8 q-14 0 -26 16z' fill='$hair'/>",
            'pigtails', 'long' => "<path d='M-26 ".($hy - 4)." q2 -26 26 -24 q24 -2 26 24 q-26 -6 -52 0z' fill='$hair'/>",
            default => '',
        };
        if (str_contains($extra, 'topi')) {
            $o .= "<path d='M-22 ".($hy - 18)." l4 -16 h36 l4 16z' fill='".$c('#e2557a')."'/><path d='M-16 ".($hy - 28)." h30' stroke='".$c('#2f8a4e')."' stroke-width='3'/>";
        }
        if (str_contains($extra, 'cap')) {
            $o .= "<path d='M-26 ".($hy - 14)." q26 -26 52 0 z' fill='".$c('#3b3b2a')."'/><rect x='-30' y='".($hy - 16)."' width='60' height='6' rx='3' fill='".$c('#22221a')."'/>";
        }
        $o .= $this->face(0, $hy, $mood);
        if (str_contains($extra, 'glasses')) {
            $g = $c('#1c1c22');
            $o .= "<circle cx='-9' cy='".($hy - 2)."' r='7' fill='none' stroke='$g' stroke-width='2'/><circle cx='9' cy='".($hy - 2)."' r='7' fill='none' stroke='$g' stroke-width='2'/><path d='M-2 ".($hy - 2)." h4' stroke='$g' stroke-width='2'/>";
        }
        if (str_contains($extra, 'shades')) {
            $o .= "<path d='M-20 ".($hy - 7)." h40 v6 q-4 8 -12 6 l-6 -6 h-4 l-6 6 q-8 2 -12 -6z' fill='".$c('#111111')."'/>";
        }
        if (in_array($mood, ['happy', 'laugh'], true) && ! $this->mono) {
            $o .= "<circle cx='-15' cy='".($hy + 8)."' r='4' fill='#ff9fb9' opacity='.6'/><circle cx='15' cy='".($hy + 8)."' r='4' fill='#ff9fb9' opacity='.6'/>";
        }

        return $o.'</g>';
    }

    private function momo(string $mood): string
    {
        $body = $this->c('#fff8ec');
        $edge = $this->c('#d9c6a5');
        $o = "<path d='M-40 0 q-6 -46 40 -60 q46 14 40 60 z' fill='$body' stroke='$edge' stroke-width='3'/>";
        $o .= "<path d='M0 -60 q-6 -10 0 -18 q6 8 0 18 M-14 -56 q-4 -8 2 -14 M14 -56 q4 -8 -2 -14' stroke='$edge' stroke-width='3' fill='none' stroke-linecap='round'/>";
        $o .= "<path d='M-22 -2 v10 M22 -2 v10' stroke='".$this->c('#8a5a33')."' stroke-width='5' stroke-linecap='round'/>";
        $o .= $this->face(0, -24, $mood, .9);
        if (! $this->mono) {
            $o .= "<circle cx='-18' cy='-16' r='4' fill='#ff9fb9' opacity='.7'/><circle cx='18' cy='-16' r='4' fill='#ff9fb9' opacity='.7'/>";
        }

        return $o;
    }

    private function yeti(string $mood): string
    {
        $fur = $this->c('#f4f6fb');
        $edge = $this->c('#aab4cf');
        $face = $this->c('#8fa2c8');
        $o = "<rect x='-30' y='-40' width='22' height='40' rx='10' fill='$fur' stroke='$edge' stroke-width='3'/><rect x='8' y='-40' width='22' height='40' rx='10' fill='$fur' stroke='$edge' stroke-width='3'/>";
        $o .= "<path d='M-46 -40 q-8 -90 46 -110 q54 20 46 110 z' fill='$fur' stroke='$edge' stroke-width='3'/>";
        $o .= "<path d='M-44 -100 l-22 40 M44 -100 l22 40' stroke='$fur' stroke-width='18' stroke-linecap='round'/>";
        $o .= "<ellipse cx='0' cy='-112' rx='24' ry='20' fill='$face'/>";
        $o .= $this->face(0, -114, $mood, 1, '#1c2238');
        $o .= "<path d='M-40 -150 l8 -10 l6 8 l8 -12 l6 10 l8 -10 l6 10 l8 -8 l4 12' fill='none' stroke='$edge' stroke-width='3'/>";

        return $o;
    }

    private function cat(string $mood): string
    {
        $f = $this->c('#f2a33a');
        $o = "<path d='M-26 0 q-4 -40 26 -42 q30 2 26 42z' fill='$f'/><path d='M26 -8 q30 -10 18 -40' stroke='$f' stroke-width='7' fill='none' stroke-linecap='round'/>";
        $o .= "<circle cx='0' cy='-56' r='20' fill='$f'/><path d='M-18 -66 l2 -18 l12 10z M18 -66 l-2 -18 l-12 10z' fill='$f'/>";
        $o .= $this->face(0, -56, $mood, .7);

        return $o;
    }

    private function robot(string $mood): string
    {
        $m = $this->c('#9aa6c8');
        $d = $this->c('#4a5578');
        $o = "<rect x='-20' y='-40' width='14' height='40' rx='4' fill='$d'/><rect x='6' y='-40' width='14' height='40' rx='4' fill='$d'/>";
        $o .= "<rect x='-32' y='-100' width='64' height='62' rx='12' fill='$m'/><rect x='-16' y='-86' width='32' height='18' rx='4' fill='".$this->c('#7fe0d2')."'/>";
        $o .= "<rect x='-30' y='-156' width='60' height='50' rx='14' fill='$m'/><path d='M0 -156 v-14' stroke='$d' stroke-width='3'/><circle cx='0' cy='-172' r='5' fill='".$this->c('#e2557a')."'/>";
        $o .= "<rect x='-22' y='-146' width='44' height='30' rx='8' fill='".$this->c('#1c2238')."'/>";
        $o .= $this->face(0, -131, $mood, .8, '#7fe0d2');

        return $o;
    }
}
