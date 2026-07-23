<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SampleChapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.chapters_enabled' => true]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeAnthropic(array $payload): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            ], 200),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile')->plainTextToken];
    }

    private function withEntries(User $user, int $count): void
    {
        Entry::factory()->count($count)->for($user)->create(['entry_date' => Carbon::parse('2025-06-15')]);
    }

    public function test_free_consenting_user_generates_one_sample(): void
    {
        $this->fakeAnthropic([
            'register' => 'neutral',
            'title' => 'Ton histoire',
            'paragraphs' => [['text' => 'Depuis le début.', 'entryRefs' => []]],
        ]);
        $user = User::factory()->optedIntoAi()->create(); // free account
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertStatus(202)
            ->assertJsonPath('status', 'generating');

        // Sync queue in tests → the job ran inline and produced the all-time chapter.
        $this->assertDatabaseHas('chapters', ['user_id' => $user->id, 'kind' => 'alltime', 'status' => 'ready']);
        $this->assertNotNull($user->fresh()->sample_chapter_generated_at);
    }

    public function test_second_sample_request_is_rejected(): void
    {
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'x', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);
        $user = User::factory()->optedIntoAi()->create();
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))->postJson('/api/ai/chapters/sample')->assertStatus(202);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertStatus(409)
            ->assertJsonPath('error', 'sample_already_used');
    }

    public function test_not_enough_entries_returns_422_without_calling_model(): void
    {
        Http::fake();
        $user = User::factory()->optedIntoAi()->create();
        $this->withEntries($user, 3);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertStatus(422)
            ->assertJsonPath('error', 'not_enough_entries')
            ->assertJsonPath('required', ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->assertNull($user->fresh()->sample_chapter_generated_at);
        Http::assertNothingSent();
    }

    public function test_subscriber_is_not_applicable(): void
    {
        Http::fake();
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertStatus(409)
            ->assertJsonPath('error', 'sample_not_applicable');

        Http::assertNothingSent();
    }

    public function test_requires_consent(): void
    {
        Http::fake();
        $user = User::factory()->create(); // opted out
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertStatus(403)
            ->assertJsonPath('error', 'consent_required');

        Http::assertNothingSent();
    }

    public function test_404_when_chapters_disabled(): void
    {
        config(['services.anthropic.chapters_enabled' => false]);
        Http::fake();
        $user = User::factory()->optedIntoAi()->create();
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))
            ->postJson('/api/ai/chapters/sample')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_refused_generation_releases_the_sample_for_retry(): void
    {
        // The model refuses → allTime() returns null → the job clears the optimistic
        // lock so the user is never left having "spent" their sample on nothing.
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response(['stop_reason' => 'refusal', 'content' => []], 200)]);
        $user = User::factory()->optedIntoAi()->create();
        $this->withEntries($user, ChapterGenerator::MIN_ALLTIME_ENTRIES);

        $this->withHeaders($this->bearer($user))->postJson('/api/ai/chapters/sample')->assertStatus(202);

        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
        $this->assertNull($user->fresh()->sample_chapter_generated_at);
    }
}
