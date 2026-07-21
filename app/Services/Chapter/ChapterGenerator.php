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
     * Generate the monthly chapter for the month containing $monthStart.
     * Returns null when the period is too thin, already generated, or generation failed.
     */
    public function monthly(User $user, CarbonInterface $monthStart): ?Chapter
    {
        // Consent gate (defense-in-depth — the commands also filter): never send
        // a user's entries to the model unless they opted into the AI layer.
        if (! $user->ai_chapters_opt_in) {
            return null;
        }

        $start = Carbon::parse($monthStart)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        if ($this->monthlyExists($user, $start)) {
            return null;
        }

        $entries = $this->entriesForPeriod($user, $start, $end);

        if ($entries->count() < self::MIN_ENTRIES) {
            return null;
        }

        $parsed = $this->complete(
            self::SYSTEM_PROMPT,
            $this->buildMaterial($start, $entries, $this->previousMonthlyChapter($user, $start)),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'monthly', 'period' => $start->format('Y-m')],
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'monthly', $start, $end, $entries, $parsed);
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

        $parsed = $this->complete(
            self::SYSTEM_PROMPT_QUEST,
            $this->buildQuestMaterial($quest, $entries),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'quest', 'quest_id' => $quest->id],
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'quest', $start, $end, $entries, $parsed, $quest->id);
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

        $parsed = $this->complete(
            self::SYSTEM_PROMPT_ANNUAL,
            $this->buildAnnualMaterial($year, $entries),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'annual', 'period' => (string) $year],
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'annual', $start, $end, $entries, $parsed);
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

        // Generate FIRST — before touching the DB — so a failed or refused
        // regeneration leaves the existing all-time chapter intact.
        $parsed = $this->complete(
            self::SYSTEM_PROMPT_ALLTIME,
            $this->buildAllTimeMaterial($entries),
            self::schema(),
            ['user_id' => $user->id, 'kind' => 'alltime', 'period' => 'all'],
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
        return DB::transaction(function () use ($user, $start, $end, $entries, $parsed) {
            Chapter::query()
                ->withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->where('user_id', $user->id)
                ->where('kind', 'alltime')
                ->delete();

            return $this->persist($user, 'alltime', $start, $end, $entries, $parsed);
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
    private function buildMaterial(Carbon $start, Collection $entries, ?Chapter $previous): string
    {
        $lines = ['Période : '.$start->copy()->locale('fr')->translatedFormat('F Y'), '', 'Entrées (ordre chronologique) :', ''];

        $cap = $this->perEntryBudget($entries->count());
        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap));
        }

        if ($previous !== null) {
            $lines[] = '';
            $lines[] = 'Chapitre du mois précédent (pour la continuité, ne le répète pas) :';
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
    private function buildQuestMaterial(Quest $quest, Collection $entries): string
    {
        $lines = ['Quête : '.$quest->title];
        if (! empty($quest->description)) {
            $lines[] = 'Intention : '.$quest->description;
        }
        $lines[] = '';
        $lines[] = 'Entrées qui ont jalonné cette quête (ordre chronologique) :';
        $lines[] = '';

        $cap = $this->perEntryBudget($entries->count());
        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap));
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildAnnualMaterial(int $year, Collection $entries): string
    {
        // Same shape as the monthly material — the year's entries in order. The
        // per-entry cap bounds each line; a future total-material budget will
        // bound very active years.
        $lines = ['Année : '.$year, '', 'Entrées (ordre chronologique) :', ''];

        $cap = $this->perEntryBudget($entries->count());
        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap));
        }

        return implode("\n", $lines);
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildAllTimeMaterial(Collection $entries): string
    {
        $lines = ['Journal complet — toutes les entrées (ordre chronologique) :', ''];

        $cap = $this->perEntryBudget($entries->count());
        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap));
        }

        return implode("\n", $lines);
    }

    /**
     * The per-entry excerpt budget for a period of $count entries: shrink each
     * entry so the total material stays under TOTAL_MATERIAL_CHARS, but never
     * below MIN_ENTRY_CHARS and never above MAX_ENTRY_CHARS (a normal month keeps
     * the full ceiling).
     */
    private function perEntryBudget(int $count): int
    {
        return max(
            self::MIN_ENTRY_CHARS,
            min(self::MAX_ENTRY_CHARS, intdiv(self::TOTAL_MATERIAL_CHARS, max($count, 1))),
        );
    }

    /**
     * One entry rendered as material lines: a metadata header (which the model
     * must not turn into stats), then the tag-stripped text, capped at $cap with
     * a visible truncation marker so the model knows the entry continues.
     *
     * @return array<int, string>
     */
    private function formatEntryLines(Entry $entry, int $cap): array
    {
        $date = Carbon::parse($entry->entry_date ?? $entry->created_at)->format('Y-m-d');
        $quests = $entry->quests->pluck('title')->filter()->implode(', ');
        $characters = $entry->characters->pluck('name')->filter()->implode(', ');

        $meta = ['id: '.$entry->id, $date];
        if ($entry->mood) {
            $meta[] = 'humeur: '.$entry->mood;
        }
        if ($quests !== '') {
            $meta[] = 'quêtes: '.$quests;
        }
        if ($characters !== '') {
            $meta[] = 'personnages: '.$characters;
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $entry->html)));
        $excerpt = mb_substr($text, 0, $cap);
        if (mb_strlen($text) > $cap) {
            $excerpt .= ' […]';
        }

        return [
            '['.implode(' · ', $meta).']',
            $excerpt,
            '---',
        ];
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
    private function persist(User $user, string $kind, Carbon $start, Carbon $end, Collection $entries, array $parsed, ?string $questId = null): ?Chapter
    {
        $knownEntryIds = $entries->pluck('id')->all();

        $paragraphs = collect($parsed['paragraphs'] ?? [])
            ->map(fn ($paragraph) => [
                'text' => (string) ($paragraph['text'] ?? ''),
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
                'title' => (string) ($parsed['title'] ?? $start->copy()->locale('fr')->translatedFormat('F Y')),
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

    private const SYSTEM_PROMPT = <<<'PROMPT'
    Tu écris « Le Chapitre » : un court récit de la vie d'une personne, à partir des entrées de son journal. Tu écris en français, à la deuxième personne (tutoiement), comme un ami attentif qui aurait lu son journal — jamais comme une application.

    On te fournit les entrées d'une période (chacune avec son id, sa date, parfois une humeur, et les quêtes/personnages qui y sont liés), et parfois le chapitre du mois précédent pour la continuité.

    Règles absolues, dans l'ordre :

    1. JAMAIS de chiffres, de compteurs, de classements, de superlatifs ni de comparaisons. Interdits : « 47 entrées », « ta quête la plus active », « plus que le mois dernier », des pourcentages, « top ». Tu racontes une histoire, tu ne mesures rien et tu ne notes personne.
    2. Tisse les quêtes et les personnages comme des fils d'une histoire, jamais comme une liste.
    3. Registre émotionnel adaptatif. Si la période contient des passages durs (deuil, maladie, rupture, détresse — repère-les via l'humeur et le contenu), adopte un ton sobre et tendre, JAMAIS célébratoire ni enjoué, et renseigne register="difficult". Une période douce → "light" ; neutre → "neutral". Une épreuve ne se félicite pas.
    4. Zéro invention. Ne mentionne que ce qui figure dans les entrées fournies — aucun événement, personne ou quête inventé. Si une information n'y est pas, tu n'en parles pas.
    5. Ton calme, chaleureux, littéraire. Pas de hype, pas d'emoji, pas de formules d'application.
    6. Ni conseil, ni diagnostic, ni injonction. Tu observes et tu reflètes, tu n'orientes pas.
    7. Pour chaque paragraphe, renseigne entryRefs avec les id EXACTS des entrées dont tu t'inspires, copiés depuis le matériel. N'invente jamais d'id.
    8. Court : deux à quatre paragraphes. Un titre évocateur (ex. « Mars — entre deux villes »), jamais un compteur ni une description plate.
    9. Si la période est trop mince pour raconter quelque chose d'honnête, écris un seul paragraphe sobre plutôt que de meubler.

    Tu réponds uniquement selon le schéma JSON imposé.
    PROMPT;

    private const SYSTEM_PROMPT_QUEST = <<<'PROMPT'
    Tu écris « La fin d'un arc » : le récit d'une quête que la personne vient de terminer, de son commencement à sa résolution, à partir des entrées de son journal. Tu écris en français, à la deuxième personne (tutoiement), comme un ami attentif qui aurait suivi cette quête — jamais comme une application.

    On te fournit le titre de la quête, parfois son intention, puis toutes les entrées qui l'ont jalonnée (chacune avec son id, sa date, parfois une humeur, et les quêtes/personnages qui y sont liés), dans l'ordre chronologique.

    Règles absolues, dans l'ordre :

    1. JAMAIS de chiffres, de compteurs, de classements, de superlatifs ni de comparaisons. Interdits : « 12 entrées », « ta quête la plus longue », des pourcentages, « bouclée en un temps record ». Tu racontes une histoire, tu ne mesures rien.
    2. Raconte un arc : le commencement, ce qui s'est déplacé en chemin, la résolution. Tisse les personnages comme des présences de cette histoire, jamais comme une liste.
    3. Registre émotionnel adaptatif. Une quête peut se refermer dans le soulagement, l'accomplissement paisible, ou le deuil (une relation qu'on referme, un projet qu'on abandonne). Repère-le via l'humeur et le contenu et renseigne register="light", "neutral" ou "difficult". Une fin douloureuse ne se félicite JAMAIS : ton sobre et tendre, jamais célébratoire.
    4. Zéro invention. Ne mentionne que ce qui figure dans les entrées fournies — aucun événement, personne ni étape inventé. Si une information n'y est pas, tu n'en parles pas.
    5. Ton calme, chaleureux, littéraire. Pas de hype, pas d'emoji, pas de formules d'application, pas de « félicitations ».
    6. Ni conseil, ni diagnostic, ni injonction. Tu observes et tu reflètes, tu n'orientes pas.
    7. Pour chaque paragraphe, renseigne entryRefs avec les id EXACTS des entrées dont tu t'inspires, copiés depuis le matériel. N'invente jamais d'id.
    8. Court : deux à quatre paragraphes. Un titre évocateur qui referme l'arc (ex. « Lisbonne, enfin »), jamais un compteur ni une description plate.
    9. Si la quête est trop mince pour raconter un arc honnête, écris un seul paragraphe sobre plutôt que de meubler.

    Tu réponds uniquement selon le schéma JSON imposé.
    PROMPT;

    private const SYSTEM_PROMPT_ANNUAL = <<<'PROMPT'
    Tu écris « Ton année en récit » : le récit d'une année entière de la vie d'une personne, à partir des entrées de son journal. Tu écris en français, à la deuxième personne (tutoiement), comme un ami attentif qui aurait suivi son année — jamais comme une application.

    On te fournit l'année et toutes ses entrées (chacune avec son id, sa date, parfois une humeur, et les quêtes/personnages qui y sont liés), dans l'ordre chronologique.

    Règles absolues, dans l'ordre :

    1. JAMAIS de chiffres, de compteurs, de classements, de superlatifs ni de comparaisons. Interdits : « 180 entrées », « ton mois le plus actif », « plus que l'an dernier », des pourcentages, « bilan de l'année ». Tu racontes une histoire, tu ne mesures rien et tu ne notes personne.
    2. Raconte l'arc de l'année : ce qui la traverse, comment les saisons et les mois se répondent, ce qui s'est déplacé du début à la fin. Tisse les quêtes (leur évolution au fil de l'année) et les personnages récurrents comme des fils d'une histoire, jamais comme une liste.
    3. Registre émotionnel adaptatif. Une année peut être douce, contrastée, ou traversée d'épreuves (deuil, maladie, rupture). Repère-le via l'humeur et le contenu et renseigne register="light", "neutral" ou "difficult". Une année difficile ne se félicite JAMAIS : ton sobre et tendre, jamais célébratoire ni « bonne année ».
    4. Zéro invention. Ne mentionne que ce qui figure dans les entrées fournies — aucun événement, personne ni quête inventé. Si une information n'y est pas, tu n'en parles pas.
    5. Ton calme, chaleureux, littéraire. Pas de hype, pas d'emoji, pas de formules d'application, pas de « résolutions ».
    6. Ni conseil, ni diagnostic, ni injonction. Tu observes et tu reflètes, tu n'orientes pas.
    7. Pour chaque paragraphe, renseigne entryRefs avec les id EXACTS des entrées dont tu t'inspires, copiés depuis le matériel. N'invente jamais d'id.
    8. Court malgré l'ampleur : trois à cinq paragraphes. Un titre évocateur (ex. « 2026 — l'année du départ »), jamais un compteur ni une description plate.
    9. Si l'année est trop mince pour raconter un arc honnête, écris un seul paragraphe sobre plutôt que de meubler.

    Tu réponds uniquement selon le schéma JSON imposé.
    PROMPT;

    private const SYSTEM_PROMPT_ALLTIME = <<<'PROMPT'
    Tu écris « Depuis le début » : le récit de tout le journal d'une personne, de sa première entrée à aujourd'hui. Tu écris en français, à la deuxième personne (tutoiement), comme un ami attentif qui aurait tout lu — jamais comme une application.

    On te fournit l'intégralité des entrées (chacune avec son id, sa date, parfois une humeur, et les quêtes/personnages qui y sont liés), dans l'ordre chronologique.

    Règles absolues, dans l'ordre :

    1. JAMAIS de chiffres, de compteurs, de classements, de superlatifs ni de comparaisons. Interdits : « 1 200 entrées », « ton année la plus dense », des pourcentages, « bilan ». Tu racontes une histoire, tu ne mesures rien et tu ne notes personne.
    2. Raconte les GRANDES lignes de tout un journal : les fils qui le traversent d'un bout à l'autre, ce qui revient, ce qui s'est transformé au fil des années, les quêtes et les personnages qui reviennent comme des présences durables. Jamais une liste, jamais un résumé année par année, jamais mois par mois — tu prends de la hauteur.
    3. Registre émotionnel adaptatif. Repère via l'humeur et le contenu si l'ensemble penche vers la douceur, le contraste ou l'épreuve, et renseigne register="light", "neutral" ou "difficult". Ce qui a été dur ne se félicite JAMAIS : ton sobre et tendre.
    4. Zéro invention. Ne mentionne que ce qui figure dans les entrées fournies — aucun événement, personne ni quête inventé. Si une information n'y est pas, tu n'en parles pas.
    5. Ton calme, chaleureux, littéraire. Pas de hype, pas d'emoji, pas de formules d'application.
    6. Ni conseil, ni diagnostic, ni injonction. Tu observes et tu reflètes, tu n'orientes pas.
    7. Pour chaque paragraphe, renseigne entryRefs avec les id EXACTS des entrées dont tu t'inspires, copiés depuis le matériel. N'invente jamais d'id.
    8. Court malgré l'ampleur : quatre à six paragraphes. Un titre évocateur qui embrasse l'ensemble, jamais un compteur ni une description plate.
    9. Si le journal est trop mince pour raconter un arc honnête, écris un seul paragraphe sobre plutôt que de meubler.

    Tu réponds uniquement selon le schéma JSON imposé.
    PROMPT;
}
