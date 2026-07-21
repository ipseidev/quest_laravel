<?php

namespace App\Services\Chat;

use App\Exceptions\ChatUnavailableException;
use App\Models\Character;
use App\Models\Entry;
use App\Models\Quest;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * "Talk to Myself" — the server-side half of the conversational AI. The client
 * sends only identifiers + the conversation history; this service reads the
 * user's own entries (server copy), builds the grounding context, calls the
 * Anthropic Messages API, and returns a grounded reply. It is a MIRROR: it
 * resurfaces the user's own words and never advises. Modeled on
 * {@see ChapterGenerator} — same Claude call shape and
 * hallucination guard (cited ids intersected with real ids) — but interactive
 * (multi-turn, free-form text, no json_schema, no queue).
 */
class ChatResponder
{
    private const MAX_ENTRY_CHARS = 1500;

    private const MIN_ENTRY_CHARS = 250;

    /** Soft ceiling on the total grounding material (cost/context guard). */
    private const TOTAL_MATERIAL_CHARS = 120000;

    /** Recent entries fed to a general chat / the interviewer. */
    private const RECENT_LIMIT = 40;

    /** Below this the interviewer has nothing personal to draw on. */
    public const MIN_INTERVIEW_ENTRIES = 3;

    /**
     * Answer one turn of a chat, grounded in the user's own entries.
     *
     * @param  array{type: string, entityId?: string|null}  $context
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{reply: string, sources: array<int, string>}
     *
     * @throws ChatUnavailableException on an infrastructure failure (→ 503).
     * @throws ModelNotFoundException on a foreign/missing entity (→ 404).
     */
    public function respond(User $user, array $context, array $messages): array
    {
        [$entries, $header] = $this->resolveContext($user, $context);

        $body = $this->call([
            'model' => (string) config('services.anthropic.chat_model'),
            'max_tokens' => (int) config('services.anthropic.chat_max_tokens', 4096),
            'thinking' => ['type' => 'adaptive'],
            'system' => $this->chatSystemPrompt($header, $entries),
            'messages' => $this->sanitizeMessages($messages),
        ], ['user_id' => $user->id, 'feature' => 'chat', 'context' => $context['type']]);

        // A refusal is a valid (if unhelpful) turn, not an outage — surface a gentle
        // line rather than a 5xx.
        if (($body['stop_reason'] ?? null) === 'refusal') {
            return ['reply' => 'Je préfère ne pas répondre à ça. On peut reprendre autrement ?', 'sources' => []];
        }

        $reply = $this->textOf($body);

        if ($reply === null) {
            throw new ChatUnavailableException('Empty or unparsable chat response');
        }

        return [
            'reply' => $reply,
            'sources' => $this->citedIds($reply, $entries),
        ];
    }

    /**
     * Generate ONE personalized interview question from the user's recent entries.
     * Returns null when there's too little to draw on or generation failed — the
     * question is optional, so the caller simply shows nothing.
     */
    public function interviewPrompt(User $user): ?string
    {
        $entries = $this->recentEntries($user);

        if ($entries->count() < self::MIN_INTERVIEW_ENTRIES) {
            return null;
        }

        try {
            $body = $this->call([
                'model' => (string) config('services.anthropic.interview_model'),
                'max_tokens' => (int) config('services.anthropic.interview_max_tokens', 512),
                // One short question needs no extended reasoning; disable thinking to
                // keep this frequent (though cached) call cheap.
                'thinking' => ['type' => 'disabled'],
                'system' => self::INTERVIEW_SYSTEM_PROMPT,
                'messages' => [['role' => 'user', 'content' => $this->buildMaterial($entries)]],
            ], ['user_id' => $user->id, 'feature' => 'interview']);
        } catch (ChatUnavailableException) {
            return null;
        }

        if (($body['stop_reason'] ?? null) === 'refusal') {
            return null;
        }

        $question = $this->textOf($body);

        return $question !== null ? trim($question) : null;
    }

    /**
     * Resolve the grounding entries + a human header for the system prompt.
     *
     * @param  array{type: string, entityId?: string|null}  $context
     * @return array{0: Collection<int, Entry>, 1: string}
     */
    private function resolveContext(User $user, array $context): array
    {
        return match ($context['type']) {
            'quest' => $this->questContext($user, (string) ($context['entityId'] ?? '')),
            'character' => $this->characterContext($user, (string) ($context['entityId'] ?? '')),
            default => [$this->recentEntries($user), 'Entrées récentes de ton journal'],
        };
    }

    /**
     * @return array{0: Collection<int, Entry>, 1: string}
     */
    private function questContext(User $user, string $questId): array
    {
        // Explicit user filter (never the request-time scope) — foreign/missing → 404.
        $quest = Quest::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->findOrFail($questId);

        $header = 'Quête : '.$quest->title;
        if (! empty($quest->description)) {
            $header .= "\nIntention : ".$quest->description;
        }

        return [$this->entityEntries($user, 'quests', $questId), $header];
    }

    /**
     * @return array{0: Collection<int, Entry>, 1: string}
     */
    private function characterContext(User $user, string $characterId): array
    {
        $character = Character::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->findOrFail($characterId);

        return [$this->entityEntries($user, 'characters', $characterId), 'Personnage : '.$character->name];
    }

    /**
     * Entries linked to a quest/character, scoped to the user explicitly.
     *
     * @param  'quests'|'characters'  $relation
     * @return Collection<int, Entry>
     */
    private function entityEntries(User $user, string $relation, string $entityId): Collection
    {
        return Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('entries.user_id', $user->id)
            ->where('entries.is_deleted', false)
            ->whereHas($relation, fn ($query) => $query
                ->withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->whereKey($entityId))
            ->with($this->linkEager())
            ->orderByRaw('COALESCE(entries.entry_date, entries.created_at)')
            ->get();
    }

    /**
     * The most recent entries, returned in chronological order for the material.
     *
     * @return Collection<int, Entry>
     */
    private function recentEntries(User $user): Collection
    {
        return Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->with($this->linkEager())
            ->orderByRaw('COALESCE(entry_date, created_at) DESC')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * @return array<string, \Closure>
     */
    private function linkEager(): array
    {
        return [
            'quests' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
            'characters' => fn ($query) => $query->withoutGlobalScope(BelongsToCurrentUserScope::class),
        ];
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function chatSystemPrompt(string $header, Collection $entries): string
    {
        $material = $entries->isEmpty()
            ? '(aucune entrée disponible pour ce contexte)'
            : $this->buildMaterial($entries);

        return self::CHAT_SYSTEM_PROMPT."\n\n".$header."\n\nEntrées (ordre chronologique) :\n\n".$material;
    }

    /**
     * @param  Collection<int, Entry>  $entries
     */
    private function buildMaterial(Collection $entries): string
    {
        $lines = [];
        $cap = $this->perEntryBudget($entries->count());

        foreach ($entries as $entry) {
            array_push($lines, ...$this->formatEntryLines($entry, $cap));
        }

        return implode("\n", $lines);
    }

    /**
     * Shrink per-entry excerpts so the total material stays under the ceiling,
     * bounded to [MIN_ENTRY_CHARS, MAX_ENTRY_CHARS]. Mirrors ChapterGenerator.
     */
    private function perEntryBudget(int $count): int
    {
        return max(
            self::MIN_ENTRY_CHARS,
            min(self::MAX_ENTRY_CHARS, intdiv(self::TOTAL_MATERIAL_CHARS, max($count, 1))),
        );
    }

    /**
     * One entry as material lines: a metadata header carrying the exact id (so the
     * model can cite it), then the tag-stripped, capped text.
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
     * Entry ids the model cited via [[entry:<id>]] markers, intersected with the
     * ids we actually fed it — a hallucinated id can never survive (same guard as
     * the chapter pipeline's entryRefs).
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<int, string>
     */
    private function citedIds(string $reply, Collection $entries): array
    {
        preg_match_all('/\[\[entry:([0-9a-fA-F-]{36})\]\]/', $reply, $matches);

        return array_values(array_unique(array_intersect($matches[1], $entries->pluck('id')->all())));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    private function sanitizeMessages(array $messages): array
    {
        return array_values(array_map(fn (array $message) => [
            'role' => ($message['role'] ?? '') === 'assistant' ? 'assistant' : 'user',
            'content' => (string) ($message['content'] ?? ''),
        ], $messages));
    }

    /**
     * Call the Anthropic Messages API and return the decoded body. Throws
     * ChatUnavailableException on any infrastructure failure — there is no queue
     * to retry an interactive request.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $logContext
     * @return array<string, mixed>
     */
    private function call(array $payload, array $logContext = []): array
    {
        try {
            $response = Http::baseUrl((string) config('services.anthropic.base_url'))
                ->withHeaders([
                    'x-api-key' => (string) config('services.anthropic.key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->timeout(120)
                ->post('/v1/messages', $payload);
        } catch (ConnectionException $e) {
            Log::warning('quest.chat.transient', $logContext + ['reason' => 'connection', 'message' => $e->getMessage()]);

            throw new ChatUnavailableException('Anthropic connection failed', previous: $e);
        }

        if ($response->failed()) {
            Log::warning('quest.chat.failed', $logContext + [
                'status' => $response->status(),
                'request_id' => $response->header('request-id') ?: $response->header('x-request-id'),
            ]);

            throw new ChatUnavailableException('Anthropic HTTP '.$response->status());
        }

        $body = $response->json();

        if (($body['stop_reason'] ?? null) === 'max_tokens') {
            Log::warning('quest.chat.truncated', $logContext);

            throw new ChatUnavailableException('Anthropic response truncated (max_tokens)');
        }

        return is_array($body) ? $body : [];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function textOf(array $body): ?string
    {
        $block = collect($body['content'] ?? [])->firstWhere('type', 'text');
        $text = is_array($block) ? ($block['text'] ?? null) : null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }

    private const CHAT_SYSTEM_PROMPT = <<<'PROMPT'
    Tu es « Se parler à soi-même » : la personne discute avec son propre journal. Tu es un MIROIR, pas un oracle. Tu ne donnes ni conseil, ni diagnostic, ni jugement, ni prédiction, ni interprétation psychologique. Tu aides la personne à retrouver, relire et relier ce qu'ELLE a écrit — jamais à lui dire quoi faire ni ce qu'elle devrait ressentir.

    On te fournit des entrées de son journal (chacune avec son id, sa date, parfois une humeur, et les quêtes/personnages liés). Tu réponds en français, à la deuxième personne (tutoiement), d'un ton calme et sobre — jamais comme une application, sans emoji, sans hype, sans félicitations.

    Règles :
    1. Zéro invention. Ne t'appuie QUE sur les entrées fournies. Si l'information n'y est pas, dis-le simplement (« je ne vois rien là-dessus dans ce que tu as écrit ») plutôt que de combler.
    2. Aucun chiffre inventé, aucun classement, aucun score, aucun superlatif. Si on te demande un décompte, ne compte que ce qui figure dans les entrées fournies et reste prudent : « d'après ce que je vois ici… ». Tu n'as pas forcément tout le journal sous les yeux — ne l'affirme jamais comme un total.
    3. Ni conseil, ni injonction, ni diagnostic. Tu observes et tu reflètes les propres mots de la personne ; tu ne l'orientes pas.
    4. Quand tu évoques une entrée précise, cite-la en insérant son marqueur exact `[[entry:<id>]]` juste après la mention, en copiant l'id depuis le matériel. N'invente JAMAIS d'id ; si tu n'es pas sûr, ne cite pas.
    5. Reste bref et concret. Pas de préambule (« Voici… », « Bien sûr… »).
    PROMPT;

    private const INTERVIEW_SYSTEM_PROMPT = <<<'PROMPT'
    Tu es l'intervieweur de « Se parler à soi-même ». À partir des entrées récentes du journal d'une personne, tu poses UNE seule question — courte, ouverte, ancrée dans ce qu'elle a réellement écrit — pour l'inviter à écrire davantage.

    Règles :
    1. UNE seule question, en français, tutoiement. Pas de préambule, pas de commentaire, pas de plusieurs questions : uniquement la question.
    2. Ancrée et spécifique : rebondis sur un fil concret des entrées (un projet, une personne, une tension, un changement). Évite les questions génériques du type « comment te sens-tu aujourd'hui ? ».
    3. Ton curieux et bienveillant, jamais évaluatif, jamais un conseil déguisé. Tu ne juges pas, tu ne notes pas, tu ne félicites pas.
    4. Zéro invention : ne t'appuie que sur les entrées fournies.
    PROMPT;
}
