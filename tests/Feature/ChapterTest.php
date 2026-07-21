<?php

namespace Tests\Feature;

use App\Exceptions\ChapterGenerationException;
use App\Jobs\GenerateAllTimeChapter;
use App\Jobs\GenerateMonthlyChapter;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Entry;
use App\Models\Quest;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChapterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeAnthropic(array $payload): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'stop_reason' => 'end_turn',
                'content' => [
                    ['type' => 'thinking', 'thinking' => '...'],
                    ['type' => 'text', 'text' => json_encode($payload)],
                ],
            ], 200),
        ]);
    }

    /**
     * A well-formed 200 body wrapping a chapter payload — one element of a
     * fakeAnthropicResponses() sequence.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function chapterBody(array $payload): array
    {
        return [
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
        ];
    }

    /**
     * Fake successive Anthropic responses. Needed when a test makes MORE THAN ONE
     * call and each must differ: Http::fake() APPENDS stubs and the first match
     * wins, so re-faking the same URL does not change the response — a sequence does.
     *
     * @param  array<int, array<string, mixed>>  $bodies
     */
    private function fakeAnthropicResponses(array ...$bodies): void
    {
        config(['services.anthropic.key' => 'test-key']);

        $sequence = Http::sequence();
        foreach ($bodies as $body) {
            $sequence->push($body, 200);
        }

        Http::fake(['api.anthropic.com/*' => $sequence]);
    }

    public function test_monthly_generation_creates_chapter_filters_refs_and_builds_threads(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $march = Carbon::parse('2026-03-01');

        $quest = Quest::factory()->for($user)->create(['title' => 'Quitter Lyon']);
        $character = Character::factory()->for($user)->create(['name' => 'Marie']);

        $entries = Entry::factory()->count(6)->for($user)->create(['entry_date' => $march->copy()->addDays(3)]);
        $linked = $entries->first();
        $linked->quests()->attach($quest->id, ['created_at' => now()]);
        $linked->characters()->attach($character->id, ['created_at' => now()]);

        $this->fakeAnthropic([
            'register' => 'difficult',
            'title' => 'Mars — entre deux villes',
            'paragraphs' => [
                ['text' => 'Ce mois, tu reviens souvent sur le départ.', 'entryRefs' => [$linked->id, 'hallucinated-id']],
            ],
        ]);

        $chapter = app(ChapterGenerator::class)->monthly($user, $march);

        $this->assertNotNull($chapter);
        $this->assertSame('monthly', $chapter->kind);
        $this->assertSame('difficult', $chapter->register);
        $this->assertSame('Mars — entre deux villes', $chapter->title);

        // The hallucinated id is stripped; only the real entry id survives.
        $this->assertSame([$linked->id], $chapter->body['paragraphs'][0]['entryRefs']);

        // Threads are built server-side from the period's links, never from the model.
        $this->assertEqualsCanonicalizing([
            ['type' => 'quest', 'id' => $quest->id, 'name' => 'Quitter Lyon'],
            ['type' => 'character', 'id' => $character->id, 'name' => 'Marie'],
        ], $chapter->threads);

        // The anti-stats guardrail is actually sent to the model.
        Http::assertSent(fn ($request) => str_contains((string) $request['system'], 'JAMAIS de chiffres'));
    }

    public function test_thin_period_skips_generation_without_calling_model(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $march = Carbon::parse('2026-03-01');
        Entry::factory()->count(3)->for($user)->create(['entry_date' => $march->copy()->addDays(3)]);

        Http::fake();
        config(['services.anthropic.key' => 'test-key']);

        $chapter = app(ChapterGenerator::class)->monthly($user, $march);

        $this->assertNull($chapter);
        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
        Http::assertNothingSent();
    }

    public function test_generation_is_idempotent(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $march = Carbon::parse('2026-03-01');
        Entry::factory()->count(6)->for($user)->create(['entry_date' => $march->copy()->addDays(3)]);

        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'Mars', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $first = app(ChapterGenerator::class)->monthly($user, $march);
        $second = app(ChapterGenerator::class)->monthly($user, $march);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->where('user_id', $user->id)->count());
    }

    public function test_chapters_index_is_isolated_between_accounts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->optedIntoAi()->create();
        $chapterA = Chapter::factory()->for($userA)->create();
        $chapterB = Chapter::factory()->for($userB)->create();

        $token = $userB->createToken('mobile')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ai/chapters')
            ->assertOk();

        $ids = collect($response->json())->pluck('id');
        $this->assertTrue($ids->contains($chapterB->id));
        $this->assertFalse($ids->contains($chapterA->id));
    }

    public function test_chapter_show_returns_unwrapped_camelcase_and_404_for_foreign(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->optedIntoAi()->create();
        $chapterA = Chapter::factory()->for($userA)->create();
        $chapterB = Chapter::factory()->for($userB)->create(['register' => 'light']);

        $token = $userB->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ai/chapters/'.$chapterB->id)
            ->assertOk()
            ->assertJsonPath('id', $chapterB->id)
            ->assertJsonPath('register', 'light')
            ->assertJsonStructure(['id', 'kind', 'periodStart', 'periodEnd', 'register', 'title', 'paragraphs', 'threads', 'status', 'generatedAt'])
            ->assertJsonMissingPath('data');

        // A foreign chapter must 404 (no existence leak), never 403.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ai/chapters/'.$chapterA->id)
            ->assertNotFound();
    }

    public function test_command_generates_for_eligible_users_only(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'Mars', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $eligible = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($eligible)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $thin = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(2)->for($thin)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertDatabaseHas('chapters', ['user_id' => $eligible->id, 'kind' => 'monthly']);
        $this->assertDatabaseMissing('chapters', ['user_id' => $thin->id]);
    }

    public function test_command_does_nothing_when_disabled(): void
    {
        config(['services.anthropic.chapters_enabled' => false]);
        Http::fake();

        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertDatabaseCount('chapters', 0);
        Http::assertNothingSent();
    }

    public function test_quest_arc_generation_sets_quest_id_filters_refs_and_builds_threads(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $quest = Quest::factory()->for($user)->create([
            'title' => 'Déménager à Lisbonne',
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-01-01'),
            'completed_at' => Carbon::parse('2026-06-01'),
        ]);
        $character = Character::factory()->for($user)->create(['name' => 'Marie']);

        $entries = Entry::factory()->count(3)->for($user)->create();
        foreach ($entries as $entry) {
            $entry->quests()->attach($quest->id, ['created_at' => now()]);
        }
        $entries->first()->characters()->attach($character->id, ['created_at' => now()]);

        $this->fakeAnthropic([
            'register' => 'light',
            'title' => 'Lisbonne, enfin',
            'paragraphs' => [
                ['text' => 'Ce que tu cherchais a fini par arriver.', 'entryRefs' => [$entries->first()->id, 'hallucinated-id']],
            ],
        ]);

        $chapter = app(ChapterGenerator::class)->questArc($user, $quest);

        $this->assertNotNull($chapter);
        $this->assertSame('quest', $chapter->kind);
        $this->assertSame($quest->id, $chapter->quest_id);
        $this->assertSame('light', $chapter->register);
        $this->assertSame('Lisbonne, enfin', $chapter->title);

        // The hallucinated id is stripped; only the real entry id survives.
        $this->assertSame([$entries->first()->id], $chapter->body['paragraphs'][0]['entryRefs']);

        // Threads are built server-side from the quest's linked entries.
        $this->assertContains(['type' => 'quest', 'id' => $quest->id, 'name' => 'Déménager à Lisbonne'], $chapter->threads);
        $this->assertContains(['type' => 'character', 'id' => $character->id, 'name' => 'Marie'], $chapter->threads);

        // The arc-specific prompt is the one actually sent.
        Http::assertSent(fn ($request) => str_contains((string) $request['system'], "fin d'un arc"));
    }

    public function test_quest_arc_skips_incomplete_and_thin_quests_without_calling_model(): void
    {
        Http::fake();
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->optedIntoAi()->create();

        // Active quest with plenty of entries → skipped (not completed).
        $active = Quest::factory()->for($user)->create(['status' => 'active']);
        foreach (Entry::factory()->count(4)->for($user)->create() as $entry) {
            $entry->quests()->attach($active->id, ['created_at' => now()]);
        }

        // Completed quest with too few linked entries → skipped (thin).
        $thin = Quest::factory()->for($user)->create(['status' => 'completed', 'completed_at' => now()]);
        foreach (Entry::factory()->count(2)->for($user)->create() as $entry) {
            $entry->quests()->attach($thin->id, ['created_at' => now()]);
        }

        $this->assertNull(app(ChapterGenerator::class)->questArc($user, $active));
        $this->assertNull(app(ChapterGenerator::class)->questArc($user, $thin));

        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
        Http::assertNothingSent();
    }

    public function test_quest_arc_generation_is_idempotent(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $quest = Quest::factory()->for($user)->create(['status' => 'completed', 'completed_at' => now(), 'started_at' => now()->subMonths(2)]);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($quest->id, ['created_at' => now()]);
        }

        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'Arc', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $first = app(ChapterGenerator::class)->questArc($user, $quest);
        $second = app(ChapterGenerator::class)->questArc($user, $quest);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(1, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->where('quest_id', $quest->id)->count());
    }

    public function test_quest_command_generates_for_eligible_completed_quests_only(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'Arc', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $user = User::factory()->optedIntoAi()->create();

        $completed = Quest::factory()->for($user)->create(['status' => 'completed', 'completed_at' => now()]);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($completed->id, ['created_at' => now()]);
        }

        $active = Quest::factory()->for($user)->create(['status' => 'active']);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($active->id, ['created_at' => now()]);
        }

        $this->artisan('quest:generate-quest-chapters')->assertSuccessful();

        $this->assertDatabaseHas('chapters', ['quest_id' => $completed->id, 'kind' => 'quest']);
        $this->assertDatabaseMissing('chapters', ['quest_id' => $active->id]);
    }

    public function test_quest_command_does_nothing_when_disabled(): void
    {
        config(['services.anthropic.chapters_enabled' => false]);
        Http::fake();

        $user = User::factory()->optedIntoAi()->create();
        $quest = Quest::factory()->for($user)->create(['status' => 'completed', 'completed_at' => now()]);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($quest->id, ['created_at' => now()]);
        }

        $this->artisan('quest:generate-quest-chapters')->assertSuccessful();

        $this->assertDatabaseCount('chapters', 0);
        Http::assertNothingSent();
    }

    // --- Consent (AI opt-in) ---

    public function test_monthly_generation_requires_user_consent(): void
    {
        $user = User::factory()->create(); // opted out (production default)
        $march = Carbon::parse('2026-03-01');
        Entry::factory()->count(6)->for($user)->create(['entry_date' => $march->copy()->addDays(3)]);

        Http::fake();
        config(['services.anthropic.key' => 'test-key']);

        $this->assertNull(app(ChapterGenerator::class)->monthly($user, $march));
        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
        Http::assertNothingSent();
    }

    public function test_quest_arc_generation_requires_user_consent(): void
    {
        $user = User::factory()->create(); // opted out
        $quest = Quest::factory()->for($user)->create([
            'status' => 'completed',
            'completed_at' => now(),
            'started_at' => now()->subMonth(),
        ]);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($quest->id, ['created_at' => now()]);
        }

        Http::fake();
        config(['services.anthropic.key' => 'test-key']);

        $this->assertNull(app(ChapterGenerator::class)->questArc($user, $quest));
        Http::assertNothingSent();
    }

    public function test_command_skips_users_without_consent(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        Http::fake();

        $user = User::factory()->create(); // opted out
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertDatabaseCount('chapters', 0);
        Http::assertNothingSent();
    }

    public function test_index_returns_empty_without_consent(): void
    {
        $user = User::factory()->create(); // opted out
        Chapter::factory()->for($user)->create();

        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ai/chapters')
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_show_returns_404_without_consent(): void
    {
        $user = User::factory()->create(); // opted out
        $chapter = Chapter::factory()->for($user)->create();

        $token = $user->createToken('mobile')->plainTextToken;

        // Their own chapter, but opted out → hidden as 404 (no existence leak, never 403).
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/ai/chapters/'.$chapter->id)
            ->assertNotFound();
    }

    public function test_patch_me_writes_and_returns_consent(): void
    {
        $user = User::factory()->create(); // opted out
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/me', ['aiChaptersOptIn' => true])
            ->assertOk()
            ->assertJsonPath('user.aiChaptersOptIn', true);
        $this->assertTrue($user->fresh()->ai_chapters_opt_in);

        // And it can be turned back off.
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/me', ['aiChaptersOptIn' => false])
            ->assertOk()
            ->assertJsonPath('user.aiChaptersOptIn', false);
        $this->assertFalse($user->fresh()->ai_chapters_opt_in);
    }

    public function test_patch_me_validates_payload(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/me', [])
            ->assertStatus(422);
    }

    public function test_me_response_exposes_consent_flag(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('user.aiChaptersOptIn', true);
    }

    // --- Generation reliability ---

    /**
     * @param  array<string, mixed>  $body
     */
    private function fakeAnthropicRaw(array $body, int $status): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake(['api.anthropic.com/*' => Http::response($body, $status)]);
    }

    private function optedUserWithEntries(): User
    {
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        return $user;
    }

    public function test_transient_http_failure_throws_for_retry(): void
    {
        $user = $this->optedUserWithEntries();
        $this->fakeAnthropicRaw(['error' => 'overloaded'], 529);

        $this->expectException(ChapterGenerationException::class);
        app(ChapterGenerator::class)->monthly($user, Carbon::parse('2026-03-01'));
    }

    public function test_permanent_http_failure_returns_null_without_retry(): void
    {
        $user = $this->optedUserWithEntries();
        $this->fakeAnthropicRaw(['error' => 'bad model id'], 400);

        $this->assertNull(app(ChapterGenerator::class)->monthly($user, Carbon::parse('2026-03-01')));
        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
    }

    public function test_max_tokens_truncation_throws_for_retry(): void
    {
        $user = $this->optedUserWithEntries();
        $this->fakeAnthropicRaw(['stop_reason' => 'max_tokens', 'content' => []], 200);

        $this->expectException(ChapterGenerationException::class);
        app(ChapterGenerator::class)->monthly($user, Carbon::parse('2026-03-01'));
    }

    public function test_unparsable_response_returns_null(): void
    {
        $user = $this->optedUserWithEntries();
        $this->fakeAnthropicRaw([
            'stop_reason' => 'end_turn',
            'content' => [['type' => 'text', 'text' => 'not json at all']],
        ], 200);

        $this->assertNull(app(ChapterGenerator::class)->monthly($user, Carbon::parse('2026-03-01')));
        $this->assertSame(0, Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->count());
    }

    public function test_refusal_returns_null(): void
    {
        $user = $this->optedUserWithEntries();
        $this->fakeAnthropicRaw(['stop_reason' => 'refusal', 'content' => []], 200);

        $this->assertNull(app(ChapterGenerator::class)->monthly($user, Carbon::parse('2026-03-01')));
    }

    // --- Annual producer ---

    public function test_annual_generation_creates_chapter(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $entries = Entry::factory()
            ->count(ChapterGenerator::MIN_ANNUAL_ENTRIES)
            ->for($user)
            ->create(['entry_date' => Carbon::parse('2026-06-15')]);

        $this->fakeAnthropic([
            'register' => 'neutral',
            'title' => '2026 — une année',
            'paragraphs' => [
                ['text' => 'Cette année, beaucoup a bougé.', 'entryRefs' => [$entries->first()->id]],
            ],
        ]);

        $chapter = app(ChapterGenerator::class)->annual($user, 2026);

        $this->assertNotNull($chapter);
        $this->assertSame('annual', $chapter->kind);
        $this->assertSame('2026 — une année', $chapter->title);
        // period_start is Jan 1 of the year — the client formats it as the year.
        $this->assertSame('2026-01-01', Carbon::parse($chapter->period_start)->format('Y-m-d'));
        Http::assertSent(fn ($request) => str_contains((string) $request['system'], 'Ton année en récit'));
    }

    public function test_annual_thin_year_skips_without_calling_model(): void
    {
        Http::fake();
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(5)->for($user)->create(['entry_date' => Carbon::parse('2026-06-15')]);

        $this->assertNull(app(ChapterGenerator::class)->annual($user, 2026));
        Http::assertNothingSent();
    }

    public function test_annual_generation_is_idempotent(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()
            ->count(ChapterGenerator::MIN_ANNUAL_ENTRIES)
            ->for($user)
            ->create(['entry_date' => Carbon::parse('2026-06-15')]);

        $this->fakeAnthropic(['register' => 'neutral', 'title' => '2026', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $first = app(ChapterGenerator::class)->annual($user, 2026);
        $second = app(ChapterGenerator::class)->annual($user, 2026);

        $this->assertNotNull($first);
        $this->assertNull($second);
        $this->assertSame(
            1,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->where('user_id', $user->id)
                ->where('kind', 'annual')
                ->count(),
        );
    }

    public function test_annual_generation_requires_user_consent(): void
    {
        Http::fake();
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->create(); // opted out
        Entry::factory()
            ->count(ChapterGenerator::MIN_ANNUAL_ENTRIES)
            ->for($user)
            ->create(['entry_date' => Carbon::parse('2026-06-15')]);

        $this->assertNull(app(ChapterGenerator::class)->annual($user, 2026));
        Http::assertNothingSent();
    }

    public function test_annual_command_generates_for_eligible_opted_in_users(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => '2026', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $eligible = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(ChapterGenerator::MIN_ANNUAL_ENTRIES)->for($eligible)->create(['entry_date' => Carbon::parse('2026-04-10')]);

        $optedOut = User::factory()->create();
        Entry::factory()->count(ChapterGenerator::MIN_ANNUAL_ENTRIES)->for($optedOut)->create(['entry_date' => Carbon::parse('2026-04-10')]);

        $this->artisan('quest:generate-annual-chapters', ['--year' => '2026'])->assertSuccessful();

        $this->assertDatabaseHas('chapters', ['user_id' => $eligible->id, 'kind' => 'annual']);
        $this->assertDatabaseMissing('chapters', ['user_id' => $optedOut->id]);
    }

    // --- Idempotency (unique index) ---

    public function test_duplicate_monthly_chapter_is_rejected_by_unique_index(): void
    {
        $user = User::factory()->create();
        $period = Carbon::parse('2026-03-01');
        Chapter::factory()->for($user)->create(['kind' => 'monthly', 'period_start' => $period, 'quest_id' => null]);

        $this->expectException(UniqueConstraintViolationException::class);
        Chapter::factory()->for($user)->create(['kind' => 'monthly', 'period_start' => $period, 'quest_id' => null]);
    }

    public function test_duplicate_quest_chapter_is_rejected_by_unique_index(): void
    {
        $user = User::factory()->create();
        $quest = Quest::factory()->for($user)->create();
        Chapter::factory()->for($user)->create(['kind' => 'quest', 'quest_id' => $quest->id]);

        $this->expectException(UniqueConstraintViolationException::class);
        Chapter::factory()->for($user)->create(['kind' => 'quest', 'quest_id' => $quest->id]);
    }

    public function test_orphaned_quest_chapters_do_not_collide_on_period_index(): void
    {
        // chapters.quest_id is nullOnDelete: hard-deleting a quest nulls its
        // chapter's quest_id. Two quest chapters sharing a period_start must NOT
        // collide on chapters_period_unique after that null-out (else the retention
        // purge's bulk quest delete would abort). Regression guard for the
        // `kind <> 'quest'` index predicate.
        $user = User::factory()->create();
        $period = Carbon::parse('2026-03-01');
        $questA = Quest::factory()->for($user)->create();
        $questB = Quest::factory()->for($user)->create();
        Chapter::factory()->for($user)->create(['kind' => 'quest', 'quest_id' => $questA->id, 'period_start' => $period]);
        Chapter::factory()->for($user)->create(['kind' => 'quest', 'quest_id' => $questB->id, 'period_start' => $period]);

        // Hard delete → FK nulls both chapters' quest_id. Without the predicate fix,
        // the second delete would throw UniqueConstraintViolationException.
        $questA->delete();
        $questB->delete();

        $this->assertSame(
            2,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->where('user_id', $user->id)->count(),
        );
    }

    // --- Backfill / idempotent dispatch ---

    public function test_monthly_backfill_generates_across_a_range(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'm', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $user = User::factory()->optedIntoAi()->create();
        foreach (['2026-01-10', '2026-02-10', '2026-03-10'] as $date) {
            Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse($date)]);
        }

        $this->artisan('quest:generate-monthly-chapters', ['--since' => '2026-01', '--until' => '2026-03'])
            ->assertSuccessful();

        $this->assertSame(
            3,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->where('user_id', $user->id)
                ->where('kind', 'monthly')
                ->count(),
        );
    }

    public function test_monthly_command_is_idempotent_on_rerun(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'm', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();
        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertSame(
            1,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)
                ->where('user_id', $user->id)
                ->where('kind', 'monthly')
                ->count(),
        );
    }

    // --- Total-material budget ---

    public function test_material_is_bounded_by_a_total_budget_on_active_periods(): void
    {
        $user = User::factory()->optedIntoAi()->create();
        $march = Carbon::parse('2026-03-01');
        // Many long entries — past the point where the per-entry ceiling alone
        // (160 * 1500 ≈ 240k) would blow the material size.
        Entry::factory()->count(160)->for($user)->create([
            'entry_date' => $march->copy()->addDays(3),
            'html' => str_repeat('mot ', 400), // ~1600 chars, > the shrunk cap
        ]);
        $this->fakeAnthropic(['register' => 'neutral', 'title' => 'Mars', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]);

        app(ChapterGenerator::class)->monthly($user, $march);

        Http::assertSent(function ($request) {
            $content = (string) $request['messages'][0]['content'];

            // The total budget shrinks per-entry excerpts; without it this would be ~240k.
            return mb_strlen($content) < 220000;
        });
    }

    // --- Dispatch-level idempotency & consent, isolated via Queue::fake ---

    public function test_monthly_command_dispatches_a_job_for_an_uncovered_user(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        Queue::fake();

        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        Queue::assertPushed(GenerateMonthlyChapter::class, 1);
    }

    public function test_monthly_command_skips_dispatch_for_an_already_covered_user(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        Queue::fake();

        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);
        // A ready chapter already exists → the command's whereNotExists must skip
        // dispatch (isolates the dispatch-level idempotency, not the job guard).
        Chapter::factory()->for($user)->create([
            'kind' => 'monthly',
            'period_start' => Carbon::parse('2026-03-01'),
            'quest_id' => null,
            'status' => 'ready',
        ]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_monthly_command_does_not_dispatch_for_opted_out_users(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        Queue::fake();

        $optedOut = User::factory()->create(); // opted out
        Entry::factory()->count(6)->for($optedOut)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        // Isolates the command's ai_chapters_opt_in filter (with Queue::fake the
        // generator gate never runs, so only the dispatch filter can block this).
        Queue::assertNothingPushed();
    }

    // --- All-time ("depuis toujours") + regeneration ---

    private function optedUserWithAllTimeEntries(): User
    {
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()
            ->count(ChapterGenerator::MIN_ALLTIME_ENTRIES)
            ->for($user)
            ->create(['entry_date' => Carbon::parse('2025-06-15')]);

        return $user;
    }

    public function test_alltime_generation_creates_chapter(): void
    {
        $user = $this->optedUserWithAllTimeEntries();
        $this->fakeAnthropic([
            'register' => 'neutral',
            'title' => 'Ton histoire',
            'paragraphs' => [['text' => 'Depuis le début, beaucoup a tenu.', 'entryRefs' => []]],
        ]);

        $chapter = app(ChapterGenerator::class)->allTime($user);

        $this->assertNotNull($chapter);
        $this->assertSame('alltime', $chapter->kind);
        $this->assertSame('Ton histoire', $chapter->title);
        Http::assertSent(fn ($request) => str_contains((string) $request['system'], 'Depuis le début'));
    }

    public function test_alltime_generation_requires_user_consent(): void
    {
        Http::fake();
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->create(); // opted out
        Entry::factory()->count(ChapterGenerator::MIN_ALLTIME_ENTRIES)->for($user)->create(['entry_date' => Carbon::parse('2025-06-15')]);

        $this->assertNull(app(ChapterGenerator::class)->allTime($user));
        Http::assertNothingSent();
    }

    public function test_alltime_skips_a_thin_journal_without_calling_model(): void
    {
        Http::fake();
        config(['services.anthropic.key' => 'test-key']);
        $user = User::factory()->optedIntoAi()->create();
        Entry::factory()->count(5)->for($user)->create(['entry_date' => Carbon::parse('2025-06-15')]);

        $this->assertNull(app(ChapterGenerator::class)->allTime($user));
        Http::assertNothingSent();
    }

    public function test_alltime_force_regenerates_replacing_the_existing_chapter(): void
    {
        $user = $this->optedUserWithAllTimeEntries();

        // Call 1 → V1 (initial), call 2 → V2 (the --force regenerate). The no-force
        // call in between makes no HTTP request (the exists guard short-circuits).
        $this->fakeAnthropicResponses(
            $this->chapterBody(['register' => 'neutral', 'title' => 'V1', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]),
            $this->chapterBody(['register' => 'neutral', 'title' => 'V2', 'paragraphs' => [['text' => 'y', 'entryRefs' => []]]]),
        );

        $first = app(ChapterGenerator::class)->allTime($user);
        $this->assertNotNull($first);
        $this->assertSame('V1', $first->title);

        // Without --force a second run is a no-op (one already exists).
        $this->assertNull(app(ChapterGenerator::class)->allTime($user));

        $second = app(ChapterGenerator::class)->allTime($user, true);

        $this->assertNotNull($second);
        $this->assertSame('V2', $second->title);
        $this->assertSame(
            1,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->where('user_id', $user->id)->where('kind', 'alltime')->count(),
        );
        // The old chapter was replaced, not kept alongside.
        $this->assertDatabaseMissing('chapters', ['id' => $first->id]);
    }

    public function test_alltime_force_preserves_the_existing_chapter_when_generation_is_refused(): void
    {
        $user = $this->optedUserWithAllTimeEntries();

        // Call 1 → a good V1. Call 2 (the --force regenerate) → a refusal.
        $this->fakeAnthropicResponses(
            $this->chapterBody(['register' => 'neutral', 'title' => 'V1', 'paragraphs' => [['text' => 'x', 'entryRefs' => []]]]),
            ['stop_reason' => 'refusal', 'content' => []],
        );

        $first = app(ChapterGenerator::class)->allTime($user);
        $this->assertNotNull($first);

        // Regeneration is refused → allTime returns null and MUST NOT destroy the
        // existing all-time chapter (generate-first, replace-only-on-success).
        $this->assertNull(app(ChapterGenerator::class)->allTime($user, true));

        // The original row is still present and unchanged (title is encrypted, so
        // assert via the decrypted model rather than a raw column match).
        $this->assertDatabaseHas('chapters', ['id' => $first->id, 'kind' => 'alltime']);
        $this->assertSame('V1', $first->fresh()->title);
        $this->assertSame(
            1,
            Chapter::withoutGlobalScope(BelongsToCurrentUserScope::class)->where('user_id', $user->id)->where('kind', 'alltime')->count(),
        );
    }

    public function test_alltime_command_dispatches_for_eligible_opted_in_user(): void
    {
        config(['services.anthropic.chapters_enabled' => true]);
        Queue::fake();

        $user = $this->optedUserWithAllTimeEntries();

        $this->artisan('quest:generate-alltime-chapters', ['--user' => $user->id])->assertSuccessful();

        Queue::assertPushed(GenerateAllTimeChapter::class, 1);
    }
}
