<?php

namespace App\Services\Chapter;

use App\Models\Chapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use Carbon\CarbonInterface;
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

    private const MAX_ENTRY_CHARS = 1500;

    /**
     * Generate the monthly chapter for the month containing $monthStart.
     * Returns null when the period is too thin, already generated, or generation failed.
     */
    public function monthly(User $user, CarbonInterface $monthStart): ?Chapter
    {
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
        );

        if ($parsed === null) {
            return null;
        }

        return $this->persist($user, 'monthly', $start, $end, $entries, $parsed);
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

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildMaterial(Carbon $start, Collection $entries, ?Chapter $previous): string
    {
        $lines = ['Période : '.$start->copy()->locale('fr')->translatedFormat('F Y'), '', 'Entrées (ordre chronologique) :', ''];

        foreach ($entries as $entry) {
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

            $lines[] = '['.implode(' · ', $meta).']';
            $lines[] = mb_substr($text, 0, self::MAX_ENTRY_CHARS);
            $lines[] = '---';
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
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>|null
     */
    private function complete(string $system, string $material, array $schema): ?array
    {
        $response = Http::baseUrl((string) config('services.anthropic.base_url'))
            ->withHeaders([
                'x-api-key' => (string) config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])
            ->timeout(120)
            ->retry(2, 1000)
            ->post('/v1/messages', [
                'model' => (string) config('services.anthropic.chapter_model'),
                'max_tokens' => 8000,
                'thinking' => ['type' => 'adaptive'],
                'system' => $system,
                'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
                'messages' => [['role' => 'user', 'content' => $material]],
            ]);

        if ($response->failed()) {
            Log::warning('quest.chapter.generate_failed', ['status' => $response->status()]);

            return null;
        }

        $body = $response->json();

        if (($body['stop_reason'] ?? null) === 'refusal') {
            Log::info('quest.chapter.refused');

            return null;
        }

        $textBlock = collect($body['content'] ?? [])->firstWhere('type', 'text');
        $text = is_array($textBlock) ? ($textBlock['text'] ?? null) : null;
        $parsed = is_string($text) ? json_decode($text, true) : null;

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * @param  Collection<int, Entry>  $entries
     * @param  array<string, mixed>  $parsed
     */
    private function persist(User $user, string $kind, Carbon $start, Carbon $end, Collection $entries, array $parsed): Chapter
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

        return Chapter::create([
            'user_id' => $user->id,
            'kind' => $kind,
            'period_start' => $start,
            'period_end' => $end,
            'quest_id' => null,
            'register' => $register,
            'title' => (string) ($parsed['title'] ?? $start->copy()->locale('fr')->translatedFormat('F Y')),
            'body' => ['paragraphs' => $paragraphs],
            'threads' => $this->threadsFrom($entries),
            'status' => 'ready',
        ]);
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
}
