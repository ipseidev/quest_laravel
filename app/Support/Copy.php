<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

/**
 * Reads marketing copy with the product's own numbers already substituted in.
 *
 * Nothing quantitative is written into a translation string: the copy says
 * `:monthly`, `:quota`, `:themes_total`, and this class fills them from
 * `config/site.php`. The reason is that the same facts appear on the pricing
 * page, in the FAQ, and inside the JSON-LD offers — three places that would
 * quietly disagree the first time someone edited a price in only two of them.
 *
 * `items()` exists because Laravel's translator applies replacements to strings
 * but returns array lines untouched, so an FAQ list read straight from the lang
 * file would render a literal ":monthly" to visitors and to crawlers.
 */
final class Copy
{
    /**
     * The substitutions available to every marketing string.
     *
     * @return array<string, string>
     */
    public static function replacements(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $plus = Plus::forLocale($locale);
        $facts = config('site.facts');

        return [
            'publisher' => (string) config('legal.publisher.name'),
            'ios_min_os' => (string) config('site.stores.ios.min_os'),
            'android_min_os' => (string) config('site.stores.android.min_os'),
            'monthly' => $plus['monthly'],
            'annual' => $plus['annual'],
            'annual_per_month' => $plus['annual_per_month'],
            'saving' => (string) $plus['annual_saving_percent'],
            'saving_amount' => $plus['annual_saving_amount'],
            'quota' => $plus['free_quota'],
            'themes_total' => (string) $facts['themes_total'],
            'themes_free' => (string) $facts['themes_free'],
            'fonts' => (string) $facts['fonts'],
            'retention' => (string) $facts['trash_retention_days'],
            'ai_provider' => $facts['ai_provider'],
            'exports' => implode(', ', $facts['export_formats']),
        ];
    }

    public static function has(string $key): bool
    {
        return Lang::has($key);
    }

    public static function text(string $key, ?string $locale = null): string
    {
        return (string) __($key, self::replacements($locale), $locale);
    }

    /**
     * An array line with replacements applied to every string inside it, at any
     * depth — so a list of `['title' => ..., 'body' => ...]` rows works.
     *
     * @return array<mixed>
     */
    public static function items(string $key, ?string $locale = null): array
    {
        $line = Lang::get($key, [], $locale);

        if (! is_array($line)) {
            return [];
        }

        return self::substitute($line, self::replacements($locale));
    }

    /**
     * @param  array<mixed>  $line
     * @param  array<string, string>  $replacements
     * @return array<mixed>
     */
    private static function substitute(array $line, array $replacements): array
    {
        $pairs = [];

        foreach ($replacements as $token => $value) {
            $pairs[':'.$token] = $value;
        }

        return array_map(
            fn (mixed $value): mixed => match (true) {
                is_array($value) => self::substitute($value, $replacements),
                is_string($value) => strtr($value, $pairs),
                default => $value,
            },
            $line,
        );
    }
}
