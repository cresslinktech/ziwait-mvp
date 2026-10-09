<?php

namespace App\Support;

use Brick\Math\RoundingMode;
use Brick\Money\Money;

/** All amounts are integer minor units; this converts to and from display strings without floats. */
class MoneyFormat
{
    /** en_NG gives the Naira sign (₦); the plain en locale prints the ISO code instead. */
    private const LOCALE = 'en_NG';

    public static function toMinor(string $amount, string $currency): int
    {
        return Money::of($amount, $currency, roundingMode: RoundingMode::HALF_UP)->getMinorAmount()->toInt();
    }

    public static function display(int $minor, string $currency): string
    {
        return Money::ofMinor($minor, $currency)->formatTo(self::LOCALE);
    }

    /** Plain decimal string for form inputs, e.g. 1250 => "12.50". */
    public static function plain(int $minor, string $currency): string
    {
        return (string) Money::ofMinor($minor, $currency)->getAmount();
    }

    /** Short form for charts and tiles: ₦12.5M, ₦850K, ₦900. Whole-unit rounding only; never used for accounting. */
    public static function compact(int $minor, string $currency): string
    {
        $money = Money::ofMinor($minor, $currency);
        $symbol = rtrim(preg_replace('/[\d.,\s]/u', '', $money->formatTo(self::LOCALE)));
        $major = (float) (string) $money->getAmount();

        return match (true) {
            $major >= 1_000_000 => $symbol.rtrim(rtrim(number_format($major / 1_000_000, 1), '0'), '.').'M',
            $major >= 1_000 => $symbol.rtrim(rtrim(number_format($major / 1_000, 1), '0'), '.').'K',
            default => $symbol.number_format($major),
        };
    }
}
