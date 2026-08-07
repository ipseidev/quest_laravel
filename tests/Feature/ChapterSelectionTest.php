<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The contract between the two passes.
 *
 * Choosing and writing are separate calls because one call asked to do both
 * hedged: it covered the whole month instead of keeping three moments. These
 * tests pin what the split is actually for — the writing pass sees the chosen
 * entries, whole, and nothing else.
 */
class ChapterSelectionTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $requests = [];

    /**
     * @param  callable(string): array<int, string>  $choose  Picks entry ids from the
     *                                                        material the selection pass is shown.
     */
    private function fakePasses(callable $choose, string $register = 'neutral'): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => function ($request) use ($choose, $register) {
            $material = (string) $request['messages'][0]['content'];
            $required = $request['output_config']['format']['schema']['required'] ?? [];
            $selecting = in_array('moments', $required, true);

            $this->requests[] = ['pass' => $selecting ? 'select' : 'write', 'material' => $material];

            $body = $selecting
                ? ['register' => $register, 'moments' => [['label' => 'un moment', 'entryRefs' => $choose($material)]]]
                : ['title' => 'Juillet', 'paragraphs' => [['text' => 'Une phrase.', 'entryRefs' => []]]];

            return Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => json_encode($body)]],
            ], 200);
        }]);
    }

    private function materialOf(string $pass): ?string
    {
        foreach ($this->requests as $r) {
            if ($r['pass'] === $pass) {
                return $r['material'];
            }
        }

        return null;
    }

    private function july(): Carbon
    {
        return Carbon::parse('2026-07-01');
    }

    public function test_the_writing_pass_sees_only_the_chosen_entries(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = $this->july();

        $entries = collect(range(1, 8))->map(fn (int $i) => Entry::factory()->for($user)->create([
            'entry_date' => $july->copy()->addDays($i),
            'html' => "<p>Journée numéro {$i} racontée ici.</p>",
        ]));

        $kept = $entries->take(2);
        $this->fakePasses(fn () => $kept->pluck('id')->all());

        $this->assertNotNull(app(ChapterGenerator::class)->monthly($user, $july));

        $selectMaterial = $this->materialOf('select');
        $writeMaterial = $this->materialOf('write');

        // Pass 1 reads the whole month.
        foreach ($entries as $i => $entry) {
            $this->assertStringContainsString('Journée numéro '.($i + 1), $selectMaterial);
        }

        // Pass 2 reads the two kept, and cannot mention what it never received.
        $this->assertStringContainsString('Journée numéro 1', $writeMaterial);
        $this->assertStringContainsString('Journée numéro 2', $writeMaterial);
        foreach (range(3, 8) as $i) {
            $this->assertStringNotContainsString("Journée numéro {$i}", $writeMaterial);
        }
    }

    public function test_the_writing_pass_gets_the_chosen_entries_whole(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = $this->july();

        // A long day: the middle is what the selection pass's 1500-char cap drops,
        // and precisely what the writing pass must get back.
        $middle = 'AU-MILIEU-DE-LA-JOURNEE';
        $long = str_repeat('a', 1400).$middle.str_repeat('b', 1400);

        $entry = Entry::factory()->for($user)->create([
            'entry_date' => $july->copy()->addDay(),
            'html' => "<p>{$long}</p>",
        ]);
        Entry::factory()->count(5)->for($user)->create(['entry_date' => $july->copy()->addDays(2)]);

        $this->fakePasses(fn () => [$entry->id]);

        app(ChapterGenerator::class)->monthly($user, $july);

        $this->assertStringNotContainsString($middle, $this->materialOf('select'));
        $this->assertStringContainsString($middle, $this->materialOf('write'));
    }

    public function test_the_writing_pass_is_told_the_register_it_must_obey(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = $this->july();

        // Heavy moods, so the floor raises the selection pass's `neutral`. What
        // matters here is that the RAISED value reaches the writing pass: the
        // whole point of resolving between the passes rather than after them.
        $entries = collect(['sad', 'angry', 'overwhelmed', 'worried', 'calm', 'joyful'])
            ->map(fn (string $mood, int $i) => Entry::factory()->for($user)->create([
                'entry_date' => $july->copy()->addDays($i),
                'mood' => $mood,
            ]));

        $this->fakePasses(fn () => [$entries->first()->id], register: 'neutral');

        $chapter = app(ChapterGenerator::class)->monthly($user, $july);

        $this->assertSame('difficult', $chapter->register);
        $this->assertStringContainsString(
            'Le registre de ce chapitre est : difficult',
            $this->materialOf('write'),
        );
        $this->assertStringContainsString('Les moments retenus', $this->materialOf('write'));
    }

    public function test_a_selection_of_hallucinated_ids_writes_nothing(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = $this->july();
        Entry::factory()->count(6)->for($user)->create(['entry_date' => $july->copy()->addDay()]);

        $this->fakePasses(fn () => ['not-a-real-id', '00000000-0000-0000-0000-000000000000']);

        // No chapter, and no second call: writing from an empty plan would hand
        // the model a blank brief, which is worse than retrying the job.
        $this->assertNull(app(ChapterGenerator::class)->monthly($user, $july));
        $this->assertNull($this->materialOf('write'));
        Http::assertSentCount(1);
    }
}
