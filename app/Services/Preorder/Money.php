<?php

namespace App\Services\Preorder;

use InvalidArgumentException;

/**
 * NPR money helpers. All arithmetic happens in integer paisa (1 Rs = 100 paisa);
 * decimals are only produced at the edges (DB columns / display).
 */
final class Money
{
    /** "180.5", "180.50", 180.5 or 180 (rupees) → 18050 paisa. Never goes through float math for strings. */
    public static function toPaisa(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }
        $s = is_string($amount) ? trim($amount) : number_format((float) $amount, 2, '.', '');
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,}))?$/', $s, $m)) {
            throw new InvalidArgumentException("Invalid money amount: {$s}");
        }
        $frac = substr(str_pad($m[3] ?? '', 3, '0'), 0, 3);       // 3 digits so we can round half-up
        $paisa = ((int) $m[2]) * 100 + intdiv((int) $frac + 5, 10);

        return ($m[1] ?? '') === '-' ? -$paisa : $paisa;
    }

    /** 18050 → "180.50" (string for decimal(12,2) columns). */
    public static function fromPaisa(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $paisa = abs($paisa);

        return sprintf('%s%d.%02d', $sign, intdiv($paisa, 100), $paisa % 100);
    }

    /** Percentage of an amount, rounded half-up to the nearest paisa (integer only). */
    public static function percent(int $paisa, int $percent): int
    {
        return intdiv($paisa * $percent + 50, 100);
    }

    /** Display: "Rs 1,234.50" (or "Rs 1,234" when whole rupees). */
    public static function format(string|int|float|null $amount, bool $isPaisa = false): string
    {
        $p = $isPaisa ? (int) $amount : self::toPaisa($amount);
        $whole = number_format(intdiv(abs($p), 100));
        $frac = abs($p) % 100;

        return ($p < 0 ? '-' : '').'Rs '.$whole.($frac ? sprintf('.%02d', $frac) : '');
    }
}
