<?php

namespace App\Support;

/**
 * Nacre Plus pricing, derived from the two amounts in `config/site.php`.
 *
 * The pricing page states the yearly plan's per-month equivalent and the saving
 * it represents against paying monthly. Both are computed here rather than typed
 * into the copy, so the page cannot end up advertising a discount that the
 * prices don't actually give — the classic way a pricing table starts lying
 * after someone edits one number.
 *
 * Formatting is done by hand instead of through `NumberFormatter` because the
 * two conventions we need are simple and worth pinning exactly: French writes
 * `6,99 €` with a non-breaking space before the symbol, English writes `€6.99`.
 */
final class Plus
{
    public static function monthly(): float
    {
        return (float) config('site.plus.monthly');
    }

    public static function annual(): float
    {
        return (float) config('site.plus.annual');
    }

    /** What the yearly plan works out to per month. */
    public static function annualPerMonth(): float
    {
        return self::annual() / 12;
    }

    /** Twelve monthly payments — the figure the yearly plan is compared against. */
    public static function twelveMonthsOfMonthly(): float
    {
        return self::monthly() * 12;
    }

    /** Saving of the yearly plan over twelve monthly payments, rounded down. */
    public static function annualSavingPercent(): int
    {
        $monthlyYear = self::twelveMonthsOfMonthly();

        if ($monthlyYear <= 0.0) {
            return 0;
        }

        return (int) floor((1 - self::annual() / $monthlyYear) * 100);
    }

    public static function annualSavingAmount(): float
    {
        return self::twelveMonthsOfMonthly() - self::annual();
    }

    /**
     * `6,99 €` in French, `€6.99` in English.
     *
     * The French form uses U+00A0 before the symbol: typographic convention, and
     * it stops a price from wrapping across two lines.
     */
    public static function format(float $amount, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $symbol = (string) config('site.plus.currency_symbol');

        if ($locale === 'fr') {
            return number_format($amount, 2, ',', "\u{202F}")."\u{A0}".$symbol;
        }

        return $symbol.number_format($amount, 2, '.', ',');
    }

    /** Free-tier media backup allowance, e.g. `500 Mo` / `500 MB`. */
    public static function freeQuota(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $mb = (int) config('site.plus.free_media_quota_mb');

        return $mb.($locale === 'fr' ? "\u{A0}Mo" : "\u{A0}MB");
    }

    /**
     * Everything the views and the structured data need, already formatted.
     *
     * @return array<string, string|int>
     */
    public static function forLocale(?string $locale = null): array
    {
        $locale ??= app()->getLocale();

        return [
            'monthly' => self::format(self::monthly(), $locale),
            'annual' => self::format(self::annual(), $locale),
            'annual_per_month' => self::format(self::annualPerMonth(), $locale),
            'annual_saving_percent' => self::annualSavingPercent(),
            'annual_saving_amount' => self::format(self::annualSavingAmount(), $locale),
            'monthly_raw' => number_format(self::monthly(), 2, '.', ''),
            'annual_raw' => number_format(self::annual(), 2, '.', ''),
            'currency' => (string) config('site.plus.currency'),
            'free_quota' => self::freeQuota($locale),
        ];
    }
}
