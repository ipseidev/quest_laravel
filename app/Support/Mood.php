<?php

namespace App\Support;

/**
 * The mood vocabulary, mirrored from the client (`src/data/types.ts`).
 *
 * The server used to treat `entries.mood` as an opaque string and paste it
 * straight into the chapter material, which had two consequences worth naming:
 *
 *  - A French chapter received English tokens (`humeur: overwhelmed`).
 *  - Nuances arrive as leaf words. Nothing told the model that `overwhelmed`
 *    belongs to the `stressed` family and `hopeful` to `grateful`, so the one
 *    signal that says how the month actually FELT reached it as a bag of
 *    unrelated adjectives — while the same call was asked to judge the
 *    chapter's register.
 *
 * Two tiers, exactly as on the client: eight bases, three nuances each. A key
 * this build doesn't know (an entry written by a newer client and synced
 * backwards) resolves to null everywhere rather than throwing.
 */
final class Mood
{
    /** @var array<string, list<string>> base => nuances */
    public const FAMILIES = [
        'empty' => ['drained', 'numb', 'bored'],
        'sad' => ['lonely', 'nostalgic', 'disappointed'],
        'stressed' => ['overwhelmed', 'pressured', 'impatient'],
        'angry' => ['frustrated', 'irritated', 'resentful'],
        'anxious' => ['worried', 'afraid', 'doubtful'],
        'calm' => ['relieved', 'focused', 'rested'],
        'grateful' => ['loved', 'proud', 'hopeful'],
        'joyful' => ['excited', 'light', 'inspired'],
    ];

    /**
     * Bases that mark a hard day.
     *
     * `empty` is deliberately NOT here. Flat is not the same as hard, and a
     * month of flatness told in the grave register would overstate it — the
     * register exists to stop the chapter being cheerful over a wound, not to
     * make every quiet month solemn.
     */
    public const HEAVY = ['sad', 'stressed', 'angry', 'anxious'];

    /** The base a stored mood belongs to, or null for an unknown key. */
    public static function base(?string $mood): ?string
    {
        if ($mood === null || $mood === '') {
            return null;
        }

        if (isset(self::FAMILIES[$mood])) {
            return $mood;
        }

        foreach (self::FAMILIES as $base => $nuances) {
            if (in_array($mood, $nuances, true)) {
                return $base;
            }
        }

        return null;
    }

    /** True when the mood belongs to a heavy family. */
    public static function isHeavy(?string $mood): bool
    {
        $base = self::base($mood);

        return $base !== null && in_array($base, self::HEAVY, true);
    }

    /**
     * The mood as the person sees it in the app, in the chapter's language —
     * with its family in parentheses when it is a nuance, so the model reads
     * the valence and not just the leaf ("Trop-plein (Stress)").
     */
    public static function label(?string $mood, string $locale): ?string
    {
        $base = self::base($mood);

        if ($base === null) {
            return null;
        }

        $leaf = (string) trans("moods.{$mood}", [], $locale);

        if ($mood === $base) {
            return $leaf;
        }

        return $leaf.' ('.trans("moods.{$base}", [], $locale).')';
    }
}
