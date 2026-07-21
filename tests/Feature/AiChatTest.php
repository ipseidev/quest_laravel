<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    private function fakeChat(string $reply, string $stopReason = 'end_turn'): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => $stopReason,
                'content' => [
                    ['type' => 'thinking', 'thinking' => '...'],
                    ['type' => 'text', 'text' => $reply],
                ],
            ], 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeChatRaw(array $body, int $status): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response($body, $status)]);
    }

    /**
     * @return array<string, string>
     */
    private function tokenHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    // --- Chat ---

    public function test_chat_replies_and_filters_hallucinated_sources(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();
        $quest = Quest::factory()->for($user)->create(['title' => 'Quitter Lyon']);
        $entry = Entry::factory()->for($user)->create();
        $entry->quests()->attach($quest->id, ['created_at' => now()]);

        // The reply cites one real id and one hallucinated (but well-formed) uuid.
        $reply = "Tu reviens souvent sur ce départ [[entry:{$entry->id}]], "
            .'et aussi [[entry:11111111-1111-4111-8111-111111111111]].';
        $this->fakeChat($reply);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'quest', 'entityId' => $quest->id],
                'messages' => [['role' => 'user', 'content' => 'Parle-moi de cette quête.']],
            ])
            ->assertOk()
            ->assertJsonPath('reply', $reply);

        // Only the real, provided id survives — the hallucinated one is stripped.
        $this->assertSame([$entry->id], $response->json('sources'));

        // The mirror guardrail is actually sent, and the quest's entries are the context.
        Http::assertSent(fn ($request) => str_contains((string) $request['system'], 'MIROIR')
            && str_contains((string) $request['system'], $entry->id)
            && ($request['thinking']['type'] ?? null) === 'adaptive');
    }

    public function test_chat_general_mode_replies_without_an_entity(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(3)->for($user)->create();

        $this->fakeChat('D\'après ce que je vois ici, tu écris surtout le soir.');

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [['role' => 'user', 'content' => 'Quand est-ce que j\'écris le plus ?']],
            ])
            ->assertOk()
            ->assertJsonStructure(['reply', 'sources']);
    }

    public function test_chat_returns_404_when_feature_disabled(): void
    {
        config(['services.anthropic.chat_enabled' => false]);
        Http::fake();
        $user = User::factory()->optedIntoAi()->create();

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [['role' => 'user', 'content' => 'salut']],
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_chat_requires_consent(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        Http::fake();
        $user = User::factory()->create(); // opted out

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [['role' => 'user', 'content' => 'salut']],
            ])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_chat_foreign_entity_is_404_without_leak(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        Http::fake();
        $owner = User::factory()->create();
        $quest = Quest::factory()->for($owner)->create();
        $intruder = User::factory()->optedIntoAi()->create();

        $this->withHeaders($this->tokenHeader($intruder))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'quest', 'entityId' => $quest->id],
                'messages' => [['role' => 'user', 'content' => 'parle-moi de ça']],
            ])
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_chat_validates_payload(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();

        // Entity type without entityId → 422.
        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'quest'],
                'messages' => [['role' => 'user', 'content' => 'x']],
            ])
            ->assertStatus(422);

        // Empty messages → 422.
        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [],
            ])
            ->assertStatus(422);
    }

    public function test_chat_infrastructure_failure_returns_503(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->for($user)->create();
        $this->fakeChatRaw(['error' => 'overloaded'], 529);

        $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [['role' => 'user', 'content' => 'salut']],
            ])
            ->assertStatus(503);
    }

    public function test_chat_refusal_returns_soft_reply(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->for($user)->create();
        $this->fakeChatRaw(['stop_reason' => 'refusal', 'content' => []], 200);

        $response = $this->withHeaders($this->tokenHeader($user))
            ->postJson('/api/ai/chat', [
                'context' => ['type' => 'general'],
                'messages' => [['role' => 'user', 'content' => 'quelque chose de limite']],
            ])
            ->assertOk()
            ->assertJsonPath('sources', []);

        $this->assertStringContainsString('Je préfère ne pas', $response->json('reply'));
    }

    // --- Interviewer ---

    public function test_interview_returns_question_and_caches_for_24h(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(3)->for($user)->create();

        $question = 'Qu\'est-ce qui te retient encore à Lyon ?';
        $this->fakeChat($question);

        // First call generates.
        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertOk()
            ->assertJsonPath('question', $question);

        // Second call is served from the 24h cache — no second model call.
        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertOk()
            ->assertJsonPath('question', $question);

        Http::assertSentCount(1);
    }

    public function test_interview_disables_thinking_and_uses_the_interview_model(): void
    {
        config([
            'services.anthropic.chat_enabled' => true,
            'services.anthropic.interview_model' => 'claude-haiku-4-5',
        ]);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(3)->for($user)->create();
        $this->fakeChat('Une question ?');

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertOk();

        Http::assertSent(fn ($request) => ($request['thinking']['type'] ?? null) === 'disabled'
            && $request['model'] === 'claude-haiku-4-5');
    }

    public function test_interview_returns_null_when_too_few_entries(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        Http::fake();
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(2)->for($user)->create(); // below MIN_INTERVIEW_ENTRIES

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertOk()
            ->assertJsonPath('question', null);

        Http::assertNothingSent();
    }

    public function test_interview_requires_consent(): void
    {
        config(['services.anthropic.chat_enabled' => true]);
        Http::fake();
        $user = User::factory()->create(); // opted out

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_interview_returns_404_when_feature_disabled(): void
    {
        config(['services.anthropic.chat_enabled' => false]);
        Http::fake();
        $user = User::factory()->optedIntoAi()->create();

        $this->withHeaders($this->tokenHeader($user))
            ->getJson('/api/ai/interview-prompt')
            ->assertNotFound();

        Http::assertNothingSent();
    }
}
