<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Character;
use App\Models\Entry;
use App\Models\Quest;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
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

    public function test_monthly_generation_creates_chapter_filters_refs_and_builds_threads(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $user = User::factory()->create();
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
        $userB = User::factory()->create();
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
        $userB = User::factory()->create();
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

        $eligible = User::factory()->create();
        Entry::factory()->count(6)->for($eligible)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $thin = User::factory()->create();
        Entry::factory()->count(2)->for($thin)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertDatabaseHas('chapters', ['user_id' => $eligible->id, 'kind' => 'monthly']);
        $this->assertDatabaseMissing('chapters', ['user_id' => $thin->id]);
    }

    public function test_command_does_nothing_when_disabled(): void
    {
        config(['services.anthropic.chapters_enabled' => false]);
        Http::fake();

        $user = User::factory()->create();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => Carbon::parse('2026-03-10')]);

        $this->artisan('quest:generate-monthly-chapters', ['--month' => '2026-03'])->assertSuccessful();

        $this->assertDatabaseCount('chapters', 0);
        Http::assertNothingSent();
    }

    public function test_quest_arc_generation_sets_quest_id_filters_refs_and_builds_threads(): void
    {
        $user = User::factory()->create();
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
        $user = User::factory()->create();

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
        $user = User::factory()->create();
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

        $user = User::factory()->create();

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

        $user = User::factory()->create();
        $quest = Quest::factory()->for($user)->create(['status' => 'completed', 'completed_at' => now()]);
        foreach (Entry::factory()->count(3)->for($user)->create() as $entry) {
            $entry->quests()->attach($quest->id, ['created_at' => now()]);
        }

        $this->artisan('quest:generate-quest-chapters')->assertSuccessful();

        $this->assertDatabaseCount('chapters', 0);
        Http::assertNothingSent();
    }
}
