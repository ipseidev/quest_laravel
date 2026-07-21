<?php

namespace App\Http\Controllers;

use App\Exceptions\ChatUnavailableException;
use App\Http\Requests\AiChatRequest;
use App\Models\User;
use App\Services\Chat\ChatResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AiChatController extends Controller
{
    public function __construct(private readonly ChatResponder $responder) {}

    /**
     * One turn of a "talk to yourself" chat, grounded in the user's own entries.
     *
     * @return array{reply: string, sources: array<int, string>}
     */
    public function chat(AiChatRequest $request): array
    {
        $this->assertAiAccess($request->user());

        try {
            return $this->responder->respond(
                $request->user(),
                $request->validated('context'),
                $request->validated('messages'),
            );
        } catch (ChatUnavailableException) {
            abort(503, 'Le service IA est momentanément indisponible.');
        }
    }

    /**
     * The interviewer's question of the day — generated lazily and cached 24h per
     * user, so a frequently-opened app is not a frequently-billed model call.
     * `question` is null when there's too little to draw on or generation failed
     * (the client then simply shows nothing — the question is optional).
     *
     * @return array{question: string|null}
     */
    public function interviewPrompt(Request $request): array
    {
        $this->assertAiAccess($request->user());

        $key = 'ai:interview:'.$request->user()->id;
        $question = Cache::get($key);

        if ($question === null) {
            $question = $this->responder->interviewPrompt($request->user());
            // Only cache a real question — never poison the cache with a failure.
            if ($question !== null) {
                Cache::put($key, $question, now()->addDay());
            }
        }

        return ['question' => $question];
    }

    /**
     * Kill switch first (404 — don't reveal a disabled feature exists), then the
     * paid + consent gate (403). AI is a paying-account-only feature.
     */
    private function assertAiAccess(User $user): void
    {
        abort_unless((bool) config('services.anthropic.chat_enabled'), 404);
        abort_unless($user->hasAiAccess(), 403);
    }
}
