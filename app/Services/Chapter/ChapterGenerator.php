<?php

namespace App\Services\Chapter;

use App\Exceptions\ChapterGenerationException;
use App\Models\Chapter;
use App\Models\Entry;
use App\Models\Quest;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChapterGenerator
{
    /**
     * Minimum entries in a period before a chapter is worth generating.
     * Below this, the narrative would be too thin to be honest.
     */
    public const MIN_ENTRIES = 6;

    /**
     * A quest arc is worth telling with fewer entries than a full month — a side
     * quest can turn on just a handful of pivotal moments.
     */
    public const MIN_QUEST_ENTRIES = 3;

    /**
     * A year needs real substance to tell as a single arc — well above a month's
     * floor. Below this the year-in-review would be too thin to be honest.
     */
    public const MIN_ANNUAL_ENTRIES = 24;

    /** An all-time recap needs a real body of writing to be worth telling. */
    public const MIN_ALLTIME_ENTRIES = 30;

    private const MAX_ENTRY_CHARS = 1500;

    /** Floor for a single entry's excerpt when a period is very active. */
    private const MIN_ENTRY_CHARS = 250;

    /**
     * Soft ceiling on the TOTAL material fed to the model (cost/context guard).
     * Per-entry excerpts shrink to keep an active month — or a whole year — under
     * this, instead of a blind per-entry cap that let an active year balloon.
     */
    private const TOTAL_MATERIAL_CHARS = 200000;

    /**
     * Roughly what one entry costs beyond its excerpt: the bracketed metadata line, the
     * entry's own title, and the separator. Subtracted from the per-entry budget so the
     * total ceiling stays a ceiling — before the title line was added, this overhead was
     * small enough to ignore, and it no longer is.
     */
    private const ENTRY_OVERHEAD_CHARS = 160;

    /**
     * Generate the monthly chapter for the month containing $monthStart.
     * Returns null when the period is too thin, already generated, or generation failed.
     *
     * When $force it REPLACES an existing chapter for that month — but only once a
     * fresh one has been produced, so a refusal or a hard failure never destroys what
     * is already there. Same contract as allTime($force). This is the loop used to
     * retune prompts against a real month; nothing schedules it.
     */
    public function monthly(User $user, CarbonInterface $monthStart, bool $force = false): ?Chapter
    {
        // Consent gate (defense-in-depth — the commands also filter): never send
        // a user's entries to the model unless they opted into the AI layer.
        if (! $user->ai_chapters_opt_in) {
            return null;
        }

        $start = Carbon::parse($monthStart)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if (! $force && $this->monthlyExists($user, $start)) {
            return null;
        }

        $entries = $this->entriesForPeriod($user, $start, $end);

        if ($entries->count() < self::MIN_ENTRIES) {
            return null;
        }

        $locale = $user->chapterLocale();

        $parsed = $this->complete(
            $this->systemPrompt('monthly', $locale),
            $this->buildMaterial($start, $entries, $this->previousMonthlyChapter($user, $start), $locale),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'monthly', 'period' => $start->format('Y-m'), 'locale' => $locale],
        );

        if ($parsed === null) {
            return null;
        }

        // Replace atomically when forcing: the fresh chapter is in hand, so dropping the
        // old row is now safe. Without the delete, `chapters_period_unique` would reject
        // the insert and persist() would log it as a lost race.
        return DB::transaction(function () use ($user, $start, $end, $entries, $parsed, $locale, $force) {
            if ($force) {
                Chapter::query()
                    ->withoutGlobalScope(BelongsToCurrentUserScope::class)
                    ->where('user_id', $user->id)
                    ->where('kind', 'monthly')
                    ->where('period_start', '>=', $start)
                    ->where('period_start', '<', $start->copy()->addMonth())
                    ->delete();
            }

            return $this->persist($user, 'monthly', $start, $end, $entries, $parsed, locale: $locale);
        });
    }

    /**
     * Generate the closing chapter for a completed quest — the story of its whole
     * arc, from the first linked entry to its resolution. Returns null when the
     * quest isn't completed, was already told, is too thin, or generation failed.
     */
    public function questArc(User $user, Quest $quest): ?Chapter
    {
        // Consent gate (defense-in-depth — the command also filters).
        if (! $user->ai_chapters_opt_in) {
            return null;
        }

        if ($quest->status !== 'completed') {
            return null;
        }

        if ($this->questArcExists($quest)) {
            return null;
        }

        $entries = $this->entriesForQuest($quest);

        if ($entries->count() < self::MIN_QUEST_ENTRIES) {
            return null;
        }

        $first = $entries->first();
        $last = $entries->last();
        $start = $quest->started_at
            ? Carbon::parse($quest->started_at)
            : Carbon::parse($first->entry_date ?? $first->created_at);
        $end = $quest->completed_at
            ? Carbon::parse($quest->completed_at)
            : Carbon::parse($last->entry_date ?? $last->created_at);

        $locale = $user->chapterLocale();

        $parsed = $this->complete(
            $this->systemPrompt('quest', $locale),
            $this->buildQuestMaterial($quest, $entries, $locale),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'quest', 'quest_id' => $quest->id, 'locale' => $locale],
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'quest', $start, $end, $entries, $parsed, $quest->id, $locale);
    }

    /**
     * Generate the annual chapter — the story of a whole year, its seasons, the
     * evolution of the through-line quests and recurring characters. Returns null
     * when the user hasn't consented, the year is already told, is too thin, or
     * generation failed.
     */
    public function annual(User $user, int $year): ?Chapter
    {
        // Consent gate (defense-in-depth — the command also filters).
        if (! $user->ai_chapters_opt_in) {
            return null;
        }

        $start = Carbon::create($year, 1, 1)->startOfDay();
        $end = $start->copy()->endOfYear();

        if ($this->annualExists($user, $start)) {
            return null;
        }

        $entries = $this->entriesForPeriod($user, $start, $end);

        if ($entries->count() < self::MIN_ANNUAL_ENTRIES) {
            return null;
        }

        $locale = $user->chapterLocale();

        $parsed = $this->complete(
            $this->systemPrompt('annual', $locale),
            $this->buildAnnualMaterial($year, $entries, $locale),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'annual', 'period' => (string) $year, 'locale' => $locale],
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'annual', $start, $end, $entries, $parsed, locale: $locale);
    }

    /**
     * Generate the "all-time" chapter — the story of a person's whole journal so
     * far, from the first entry to now: the throughlines across years, what
     * recurs, the characters who stay. On-demand only (no scheduler). When
     * $force it REPLACES any existing all-time chapter — but only after a fresh
     * one is successfully generated, so a failed/refused run never destroys the
     * old one. Returns null when not consented, already present (without force),
     * too thin, or generation failed.
     */
    public function allTime(User $user, bool $force = false): ?Chapter
    {
        // Consent gate (defense-in-depth — the command also filters).
        if (! $user->ai_chapters_opt_in) {
            return null;
        }

        if (! $force && $this->allTimeExists($user)) {
            return null;
        }

        $entries = $this->entriesForAllTime($user);

        if ($entries->count() < self::MIN_ALLTIME_ENTRIES) {
            return null;
        }

        $locale = $user->chapterLocale();

        // Generate FIRST — before touching the DB — so a failed or refused
        // regeneration leaves the existing all-time chapter intact.
        $parsed = $this->complete(
            $this->systemPrompt('alltime', $locale),
            $this->buildAllTimeMaterial($entries, $locale),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'alltime', 'period' => 'all', 'locale' => $locale],
        );

        if ($parsed === null) {
            return null;
        }

        $first = $entries->first();
        $last = $entries->last();
        $start = Carbon::parse($first->entry_date ?? $first->created_at);
        $end = Carbon::parse($last->entry_date ?? $last->created_at);

        // Replace atomically: drop any existing all-time chapter, then persist the
        // fresh one (there is only ever one all-time chapter per user).
        return DB::transaction(function () use ($user, $start, $end, $entries, $parsed, $locale) {
            Chapter::query()
                ->withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->where('user_id', $user->id)
                ->where('kind', 'alltime')
                ->delete();

            return $this->persist($user, 'alltime', $start, $end, $entries, $parsed, locale: $locale);
        });
    }

    /**
     * @return Collection<int, Entry>
     */
    private function entriesForAllTime(User $user): Collection
    {
        return Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->with([
                'quests' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
                'characters' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
            ])
            ->orderByRaw('COALESCE(entry_date, created_at)')
            ->get();
    }

    /**
     * @return Collection<int, Entry>
     */
    private function entriesForPeriod(User $user, Carbon $start, Carbon $end): Collection
    {
        return Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->whereBetween(DB::raw('COALESCE(entry_date, created_at)'), [$start, $end])
            ->with([
                'quests' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
                'characters' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
            ])
            ->orderByRaw('COALESCE(entry_date, created_at)')
            ->get();
    }

    /**
     * @return Collection<int, Entry>
     */
    private function entriesForQuest(Quest $quest): Collection
    {
        return $quest->entries()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('entries.is_deleted', false)
            ->with([
                'quests' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
                'characters' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
            ])
            ->orderByRaw('COALESCE(entries.entry_date, entries.created_at)')
            ->get();
    }

    private function previousMonthlyChapter(User $user, Carbon $start): ?Chapter
    {
        return Chapter::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('kind', 'monthly')
            ->where('status', 'ready')
            ->where('period_start', '<', $start)
            ->orderByDesc('period_start')
            ->first();
    }

    private function monthlyExists(User $user, Carbon $start): bool
    {
        // Half-open month range, not equality: period_start is a timestamp(3) column and an
        // equality binding (formatted without milliseconds) would miss the stored ".000" value.
        return Chapter::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('kind', 'monthly')
            ->where('period_start', '>=', $start)
            ->where('period_start', '<', $start->copy()->addMonth())
            ->where('status', 'ready')
            ->exists();
    }

    private function questArcExists(Quest $quest): bool
    {
        return Chapter::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $quest->user_id)
            ->where('kind', 'quest')
            ->where('quest_id', $quest->id)
            ->where('status', 'ready')
            ->exists();
    }

    private function annualExists(User $user, Carbon $start): bool
    {
        // Half-open year range, mirroring monthlyExists (period_start is timestamp(3)).
        return Chapter::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('kind', 'annual')
            ->where('period_start', '>=', $start)
            ->where('period_start', '<', $start->copy()->addYear())
            ->where('status', 'ready')
            ->exists();
    }

    private function allTimeExists(User $user): bool
    {
        return Chapter::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('kind', 'alltime')
            ->where('status', 'ready')
            ->exists();
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildMaterial(Carbon $start, Collection $entries, ?Chapter $previous, string $locale): string
    {
        $lines = [
            $this->label('period', $locale, ['period' => $this->monthLabel($start, $locale)]),
            '',
        ];

        array_push($lines, ...$this->rosterLines($entries, $locale));
        array_push($lines, ...$this->entryLines($entries, $locale, 'entries_heading'));

        if ($previous !== null) {
            $lines[] = '';
            $lines[] = $this->label('previous_heading', $locale);
            $lines[] = $previous->title;
            foreach (($previous->body['paragraphs'] ?? []) as $paragraph) {
                $lines[] = (string) ($paragraph['text'] ?? '');
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildQuestMaterial(Quest $quest, Collection $entries, string $locale): string
    {
        $lines = [$this->label('quest', $locale, ['title' => (string) $quest->title])];

        if (filled($quest->description)) {
            $lines[] = $this->label('quest_intent', $locale, ['intent' => $this->collapse((string) $quest->description)]);
        }

        $lines[] = '';

        array_push($lines, ...$this->rosterLines($entries, $locale, includeQuests: false));
        array_push($lines, ...$this->entryLines($entries, $locale, 'quest_entries_heading'));

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildAnnualMaterial(int $year, Collection $entries, string $locale): string
    {
        // Same shape as the monthly material — the year's entries in order, preceded by
        // the roster. The per-entry cap bounds each line; a future total-material budget
        // will bound very active years.
        $lines = [$this->label('year', $locale, ['year' => (string) $year]), ''];

        array_push($lines, ...$this->rosterLines($entries, $locale));
        array_push($lines, ...$this->entryLines($entries, $locale, 'entries_heading'));

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildAllTimeMaterial(Collection $entries, string $locale): string
    {
        $lines = [$this->label('all_time', $locale), ''];

        array_push($lines, ...$this->rosterLines($entries, $locale));
        array_push($lines, ...$this->entryLines($entries, $locale, 'entries_heading'));

        return implode("\n", $lines);
    }

    /**
     * The heading plus every entry, budgeted. Shared by the four material builders so
     * the entry block is byte-identical across kinds.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<int, string>
     */
    private function entryLines(Collection $entries, string $locale, string $headingKey): array
    {
        $lines = [$this->label($headingKey, $locale), ''];

        $cap = $this->perEntryBudget($entries->count());
        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap, $locale));
        }

        return $lines;
    }

    /**
     * The cast list, stated once at the top of the material: every quest and character
     * the period touches, with the context the entries themselves never restate — a
     * quest's intent and status, a character's relationship and note.
     *
     * Without this the model only ever sees bare names repeated in each entry header,
     * which is why it used to name people instead of weaving them. The ids stay out:
     * `threads` is built server-side from the same relations, and the model has no use
     * for a quest id it must never print.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<int, string>
     */
    private function rosterLines(Collection $entries, string $locale, bool $includeQuests = true): array
    {
        $quests = [];
        $characters = [];

        foreach ($entries as $entry) {
            foreach ($entry->quests as $quest) {
                $status = $quest->status === 'completed' ? 'status_completed' : 'status_active';
                $line = '- '.$quest->title.' ('.$this->label($status, $locale).')';
                if (filled($quest->description)) {
                    $line .= ' · '.$this->collapse((string) $quest->description);
                }
                $quests[$quest->id] = $line;
            }

            foreach ($entry->characters as $character) {
                $line = '- '.$character->name;
                if (filled($character->relationship)) {
                    $line .= ' ('.$this->collapse((string) $character->relationship).')';
                }
                if (filled($character->note)) {
                    $line .= ' · '.$this->collapse((string) $character->note);
                }
                $characters[$character->id] = $line;
            }
        }

        $lines = [];

        if ($includeQuests && $quests !== []) {
            $lines[] = $this->label('quests_heading', $locale);
            array_push($lines, ...array_values($quests));
            $lines[] = '';
        }

        if ($characters !== []) {
            $lines[] = $this->label('characters_heading', $locale);
            array_push($lines, ...array_values($characters));
            $lines[] = '';
        }

        return $lines;
    }

    /**
     * The per-entry excerpt budget for a period of $count entries: shrink each
     * entry so the total material stays under TOTAL_MATERIAL_CHARS, but never
     * below MIN_ENTRY_CHARS and never above MAX_ENTRY_CHARS (a normal month keeps
     * the full ceiling).
     */
    private function perEntryBudget(int $count): int
    {
        $share = intdiv(self::TOTAL_MATERIAL_CHARS, max($count, 1)) - self::ENTRY_OVERHEAD_CHARS;

        return max(self::MIN_ENTRY_CHARS, min(self::MAX_ENTRY_CHARS, $share));
    }

    /**
     * One entry rendered as material lines: a metadata header (which the model
     * must not turn into stats), then the tag-stripped text, capped at $cap with
     * a visible truncation marker so the model knows the entry continues.
     *
     * @return array<int, string>
     */
    private function formatEntryLines(Entry $entry, int $cap, string $locale): array
    {
        // Weekday included on purpose: "samedi 12 juillet" tells the model something
        // "2026-07-12" does not, and a run of weekday entries reads differently from a
        // run of weekend ones.
        $date = Carbon::parse($entry->entry_date ?? $entry->created_at)
            ->locale($locale)
            ->translatedFormat($this->label('date_format', $locale));

        $meta = ['id: '.$entry->id, $date];

        if ($entry->mood) {
            $meta[] = $this->label('mood', $locale).': '.$entry->mood;
        }

        $quests = $entry->quests->pluck('title')->filter()->implode(', ');
        if ($quests !== '') {
            $meta[] = $this->label('quests', $locale).': '.$quests;
        }

        $characters = $entry->characters->pluck('name')->filter()->implode(', ');
        if ($characters !== '') {
            $meta[] = $this->label('characters', $locale).': '.$characters;
        }

        $lines = ['['.implode(' · ', $meta).']'];

        // The entry's own title was never sent before. It is frequently the densest
        // line the person wrote — the one place they already summarised their day.
        if (filled($entry->title)) {
            $lines[] = trim((string) $entry->title);
        }

        $lines[] = $this->excerpt((string) $entry->html, $cap);
        $lines[] = '---';

        return $lines;
    }

    /**
     * An entry's body as the model should see it.
     *
     * Two things this deliberately does NOT do, both of which it used to:
     *
     * - It does not flatten the text to one line. The author's own paragraph breaks
     *   carry rhythm — a five-line burst reads differently from one dense block — and
     *   collapsing every `\s+` to a space threw that away before the model saw it.
     * - It does not truncate from the front only. An entry usually *resolves* at the
     *   end, so a head-only cap discarded precisely the part worth telling. Long
     *   entries are cut from the middle, keeping both the setup and the landing.
     */
    private function excerpt(string $html, int $cap): string
    {
        // Turn block boundaries into newlines BEFORE stripping, or a wall of <p> collapses.
        $text = preg_replace('#<(?:br\s*/?|/p|/div|/li|/h[1-6]|/blockquote)\s*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $text = str_replace("\r\n", "\n", $text);
        $text = preg_replace('/[^\S\n]+/u', ' ', $text);      // horizontal whitespace only
        $text = preg_replace('/ *\n */', "\n", (string) $text);
        $text = trim((string) preg_replace('/\n{3,}/', "\n\n", (string) $text));

        if (mb_strlen($text) <= $cap) {
            return $text;
        }

        $head = (int) round($cap * 0.6);

        return mb_substr($text, 0, $head)."\n[…]\n".mb_substr($text, -($cap - $head));
    }

    /**
     * Remove the dashes the prompt forbids, on the way into the DB.
     *
     * The em dash is the punctuation that most reliably reads as machine-written, and a
     * prompt rule is only ever probabilistic. So it is fought on three fronts: the rule
     * itself, the prompts and the material being dash-free (a model imitates the register
     * of its own instructions, and telling it to avoid a character used twenty times in
     * the brief does not work), and this pass, which is not probabilistic at all.
     *
     * A dash becomes a comma — grammatical in the aside and apposition slots a model
     * actually reaches for it in — and the doubled or dangling punctuation that leaves
     * behind is then collapsed. Hyphen-minus is untouched, so "chez-toi" survives; the
     * range covers figure dash through horizontal bar.
     */
    private function stripDashes(string $text): string
    {
        $text = preg_replace('/\h*[\x{2012}-\x{2015}]\h*/u', ', ', $text);
        $text = preg_replace('/([,;:.!?…])\h*,\h*/u', '$1 ', (string) $text);
        $text = preg_replace('/,\h*(?=[.!?…,;:])/u', '', (string) $text);
        $text = preg_replace('/(^\h*,\h*|\h*,\h*$)/u', '', (string) $text);

        return trim((string) preg_replace('/\h{2,}/u', ' ', (string) $text));
    }

    /** Single-line, length-bounded rendering for roster context (intents, notes). */
    private function collapse(string $value, int $cap = 240): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));

        return mb_strlen($text) > $cap ? mb_substr($text, 0, $cap).' […]' : $text;
    }

    /**
     * The system prompt for a kind, in the user's language. Prompts live in
     * `lang/{locale}/chapters.php` rather than in this class: they are product copy
     * that gets tuned far more often than the code around them, and there is one file
     * per language.
     */
    private function systemPrompt(string $kind, string $locale): string
    {
        return (string) trans("chapters.system.{$kind}", [], $locale);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function label(string $key, string $locale, array $replace = []): string
    {
        return (string) trans("chapters.material.{$key}", $replace, $locale);
    }

    private function monthLabel(Carbon $date, string $locale): string
    {
        return $date->copy()->locale($locale)->translatedFormat($this->label('month_format', $locale));
    }

    /**
     * Call the model and parse the structured response.
     *
     * Returns the parsed payload, or null for a NON-retryable "no chapter"
     * outcome (refusal, permanent 4xx, or malformed JSON). Throws
     * ChapterGenerationException for TRANSIENT failures (5xx/429/408/529,
     * connection error, max_tokens truncation) so the queued job retries.
     * Every terminal path is logged with $logContext — previously the
     * malformed/truncated-JSON path was completely silent.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>|null
     */
    private function complete(string $system, string $material, array $schema, array $logContext = []): ?array
    {
        try {
            $response = Http::baseUrl((string) config('services.anthropic.base_url'))
                ->withHeaders([
                    'x-api-key' => (string) config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->timeout(120)
                // No ->retry() here on purpose: it converts a failed HTTP response
                // into a thrown RequestException (bypassing the transient-vs-permanent
                // classification below) and would retry a permanent 4xx pointlessly.
                // Retries are owned by the job ($tries + backoff), scoped to the
                // transient ChapterGenerationException thrown below.
                ->post('/v1/messages', [
                    'model' => (string) config('services.anthropic.chapter_model'),
                    'max_tokens' => (int) config('services.anthropic.chapter_max_tokens', 16000),
                    'thinking' => ['type' => 'adaptive'],
                    'system' => $system,
                    'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
                    'messages' => [['role' => 'user', 'content' => $material]],
                ]);
        } catch (ConnectionException $e) {
            // ->retry() already exhausted its attempts for connection/timeout errors.
            Log::warning('quest.chapter.generate_transient', $logContext + [
                'reason' => 'connection',
                'message' => $e->getMessage(),
            ]);

            throw new ChapterGenerationException('Anthropic connection failed', previous: $e);
        }

        if ($response->failed()) {
            $status = $response->status();
            // ->retry() without ->throw() does NOT retry HTTP error responses, so
            // classify here: transient statuses drive a job retry, the rest are terminal.
            $transient = in_array($status, [408, 429, 500, 502, 503, 504, 529], true);

            Log::warning($transient ? 'quest.chapter.generate_transient' : 'quest.chapter.generate_failed', $logContext + [
                'status' => $status,
                'request_id' => $response->header('request-id') ?: $response->header('x-request-id'),
                'body' => $response->json(),
            ]);

            if ($transient) {
                throw new ChapterGenerationException("Anthropic HTTP {$status}");
            }

            return null;
        }

        $body = $response->json();
        $stopReason = $body['stop_reason'] ?? null;

        if ($stopReason === 'refusal') {
            Log::info('quest.chapter.refused', $logContext + [
                'category' => $body['stop_details']['category'] ?? null,
            ]);

            return null;
        }

        if ($stopReason === 'max_tokens') {
            // Adaptive thinking shares the max_tokens budget; a long think can
            // truncate the JSON output. Retry; if it persists, raise
            // ANTHROPIC_CHAPTER_MAX_TOKENS.
            Log::warning('quest.chapter.generate_transient', $logContext + ['reason' => 'max_tokens']);

            throw new ChapterGenerationException('Anthropic response truncated (max_tokens)');
        }

        $textBlock = collect($body['content'] ?? [])->firstWhere('type', 'text');
        $text = is_array($textBlock) ? ($textBlock['text'] ?? null) : null;
        $parsed = is_string($text) ? json_decode($text, true) : null;

        if (! is_array($parsed)) {
            // Previously silent: a 200 with a missing/malformed text block lost the
            // chapter with no trace. Not retryable — a retry won't fix bad JSON.
            Log::warning('quest.chapter.generate_unparsable', $logContext + [
                'stop_reason' => $stopReason,
                'had_text_block' => is_string($text),
            ]);

            return null;
        }

        return $parsed;
    }

    /**
     * @param  Collection<int, Entry>  $entries
     * @param  array<string, mixed>  $parsed
     */
    private function persist(User $user, string $kind, Carbon $start, Carbon $end, Collection $entries, array $parsed, ?string $questId = null, string $locale = User::DEFAULT_LOCALE): ?Chapter
    {
        $knownEntryIds = $entries->pluck('id')->all();

        $paragraphs = collect($parsed['paragraphs'] ?? [])
            ->map(fn ($paragraph) => [
                'text' => $this->stripDashes((string) ($paragraph['text'] ?? '')),
                'entryRefs' => array_values(array_intersect((array) ($paragraph['entryRefs'] ?? []), $knownEntryIds)),
            ])
            ->filter(fn ($paragraph) => $paragraph['text'] !== '')
            ->values()
            ->all();

        $register = in_array($parsed['register'] ?? null, ['light', 'neutral', 'difficult'], true)
            ? $parsed['register']
            : 'neutral';

        try {
            return Chapter::create([
                'user_id' => $user->id,
                'kind' => $kind,
                'period_start' => $start,
                'period_end' => $end,
                'quest_id' => $questId,
                'register' => $register,
                'title' => $this->stripDashes((string) ($parsed['title'] ?? $this->monthLabel($start, $locale))),
                'body' => ['paragraphs' => $paragraphs],
                'threads' => $this->threadsFrom($entries),
                'status' => 'ready',
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent job won the race; the partial unique index rejected the
            // duplicate. Treat as already-generated (the winning row is present) so
            // the job completes instead of retrying.
            Log::info('quest.chapter.duplicate_skipped', [
                'user_id' => $user->id,
                'kind' => $kind,
                'quest_id' => $questId,
            ]);

            return null;
        }
    }

    /**
     * Built server-side from the period's linked quests/characters — never from the model,
     * so it cannot invent threads. Used only for UI accents (no counts, no ranking).
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<int, array{type: string, id: string, name: string}>
     */
    private function threadsFrom(Collection $entries): array
    {
        $threads = [];

        foreach ($entries as $entry) {
            foreach ($entry->quests as $quest) {
                $threads['quest:'.$quest->id] = ['type' => 'quest', 'id' => $quest->id, 'name' => (string) $quest->title];
            }
            foreach ($entry->characters as $character) {
                $threads['character:'.$character->id] = ['type' => 'character', 'id' => $character->id, 'name' => (string) $character->name];
            }
        }

        return array_values($threads);
    }

    /**
     * @return array<string, mixed>
     */
    private static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['register', 'title', 'paragraphs'],
            'properties' => [
                'register' => ['type' => 'string', 'enum' => ['light', 'neutral', 'difficult']],
                'title' => ['type' => 'string'],
                'paragraphs' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['text', 'entryRefs'],
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'entryRefs' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                    ],
                ],
            ],
        ];
    }
}
