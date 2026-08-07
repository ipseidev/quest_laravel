<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Entry;
use App\Models\User;
use App\Services\Chapter\ChapterGenerator;
use App\Support\Mood;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The register floor, and the two things that feed it.
 *
 * Motivated by a real chapter: a July holding a collapsed company, broken
 * plates and a night in a hotel came back `neutral`, and the model was not
 * being careless — the prompt defined `neutral` as "ordinary contrast", which
 * is exactly what a month of highs and lows looks like from above.
 */
class ChapterRegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One generation, two calls. The stub answers by schema: the selection pass
     * declares the register the test wants tested, the writing pass returns the
     * prose. Moments are picked from the ids the material actually shows.
     */
    private function fakeAnthropic(string $register): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => function ($request) use ($register) {
            $required = $request['output_config']['format']['schema']['required'] ?? [];

            if (in_array('moments', $required, true)) {
                preg_match_all('/id: ([0-9a-f-]{36})/', (string) $request['messages'][0]['content'], $matches);
                $body = [
                    'register' => $register,
                    'moments' => [[
                        'label' => 'un moment',
                        'entryRefs' => array_slice(array_values(array_unique($matches[1])), 0, 2),
                    ]],
                ];
            } else {
                $body = ['title' => 'Juillet', 'paragraphs' => [['text' => 'Quelque chose est arrivé.', 'entryRefs' => []]]];
            }

            return Http::response([
                'stop_reason' => 'end_turn',
                'content' => [['type' => 'text', 'text' => json_encode($body)]],
            ], 200);
        }]);
    }

    /** @param  list<?string>  $moods */
    private function monthOfMoods(User $user, Carbon $month, array $moods): void
    {
        foreach ($moods as $i => $mood) {
            Entry::factory()->for($user)->create([
                'entry_date' => $month->copy()->addDays($i % 27),
                'mood' => $mood,
            ]);
        }
    }

    private function generate(User $user, Carbon $month, string $modelRegister): ?Chapter
    {
        $this->fakeAnthropic($modelRegister);

        return app(ChapterGenerator::class)->monthly($user, $month);
    }

    public function test_heavy_moods_force_the_grave_register_over_the_models_call(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        // 4 heavy out of 6 tagged = 67%, over the 40% share and the 4-entry sample.
        $this->monthOfMoods($user, $july, [
            'sad', 'angry', 'overwhelmed', 'worried', 'joyful', 'calm',
        ]);

        $chapter = $this->generate($user, $july, 'neutral');

        $this->assertNotNull($chapter);
        $this->assertSame('difficult', $chapter->register);
    }

    public function test_a_nuance_counts_as_its_family(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        // Not one base key among them: all four are nuances of heavy families.
        $this->monthOfMoods($user, $july, [
            'overwhelmed', 'lonely', 'frustrated', 'afraid', 'proud', 'rested',
        ]);

        $this->assertSame('difficult', $this->generate($user, $july, 'light')->register);
    }

    public function test_the_floor_only_ever_escalates(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        // A month of light moods must not pull a `difficult` call back down: the
        // entries can be grave in ways nobody thought to tag.
        $this->monthOfMoods($user, $july, [
            'joyful', 'calm', 'grateful', 'proud', 'light', 'rested',
        ]);

        $this->assertSame('difficult', $this->generate($user, $july, 'difficult')->register);
    }

    public function test_a_light_month_keeps_the_models_call(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        $this->monthOfMoods($user, $july, [
            'joyful', 'calm', 'grateful', 'proud', 'light', 'rested',
        ]);

        $this->assertSame('light', $this->generate($user, $july, 'light')->register);
    }

    public function test_too_few_tagged_entries_leave_the_call_alone(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        // Three heavy moods out of six entries: unanimous among the tagged, but
        // under the minimum sample. Not enough signal to overrule the reader.
        $this->monthOfMoods($user, $july, ['sad', 'angry', 'anxious', null, null, null]);

        $this->assertSame('neutral', $this->generate($user, $july, 'neutral')->register);
    }

    public function test_emptiness_is_not_hardship(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        // A flat month told in the grave register would overstate it.
        $this->monthOfMoods($user, $july, ['empty', 'numb', 'bored', 'drained', 'calm', 'rested']);

        $this->assertSame('neutral', $this->generate($user, $july, 'neutral')->register);
    }

    public function test_the_material_names_the_mood_in_the_chapters_language_with_its_family(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create(['locale' => 'fr']);
        $july = Carbon::parse('2026-07-01');

        $this->monthOfMoods($user, $july, ['overwhelmed', 'sad', 'calm', 'joyful', 'proud', 'rested']);
        $this->generate($user, $july, 'neutral');

        Http::assertSent(function ($request) {
            $material = json_encode($request['messages']);

            // The nuance, translated, carrying its family — not the raw key.
            return str_contains($material, 'Trop-plein (Stress)')
                && str_contains($material, 'Tristesse')
                && ! str_contains($material, 'overwhelmed');
        });
    }

    /**
     * Structured outputs accept a subset of JSON Schema, and reject the rest with
     * a 400 rather than ignoring it. A rejected keyword is invisible here: the
     * faked transport answers whatever schema it is handed, so the suite stayed
     * green while every real call failed and no chapter was written at all. That
     * is precisely what `maxItems` did in production.
     *
     * `minItems` IS accepted — verified against the live API, not assumed — so the
     * floor stays and only the ceiling moved into PHP.
     */
    public function test_the_schemas_use_only_keywords_structured_outputs_accept(): void
    {
        $user = User::factory()->optedIntoAi()->subscribed()->create();
        $july = Carbon::parse('2026-07-01');

        $this->monthOfMoods($user, $july, ['calm', 'joyful', 'proud', 'rested', 'light', 'focused']);
        $this->generate($user, $july, 'neutral');

        $schemas = collect(Http::recorded())
            ->map(fn (array $pair) => $pair[0]['output_config']['format']['schema'] ?? null)
            ->filter()
            ->values();

        // Both passes, or the guard is only covering half the surface.
        $this->assertCount(2, $schemas);

        foreach ($schemas as $schema) {
            $encoded = json_encode($schema);

            foreach (['maxItems', 'minLength', 'maxLength', 'pattern', 'minimum', 'maximum', 'multipleOf'] as $rejected) {
                $this->assertStringNotContainsString(
                    '"'.$rejected.'"',
                    $encoded,
                    "Structured outputs reject `{$rejected}`: the request 400s and the chapter is silently dropped.",
                );
            }
        }

        $moments = $schemas->first()['properties']['moments'] ?? null;
        $this->assertNotNull($moments);
        // 1, not 3: a thin month gets its single sober paragraph rather than
        // being padded up to a quota.
        $this->assertSame(1, $moments['minItems']);
        $this->assertContains('moments', $schemas->first()['required']);
    }

    public function test_mood_vocabulary_resolves_both_tiers(): void
    {
        $this->assertSame('stressed', Mood::base('overwhelmed'));
        $this->assertSame('stressed', Mood::base('stressed'));
        $this->assertNull(Mood::base('from-a-newer-client'));
        $this->assertNull(Mood::base(null));

        $this->assertTrue(Mood::isHeavy('lonely'));
        $this->assertFalse(Mood::isHeavy('numb'));
        $this->assertFalse(Mood::isHeavy('from-a-newer-client'));

        $this->assertSame('Trop-plein (Stress)', Mood::label('overwhelmed', 'fr'));
        $this->assertSame('Stress', Mood::label('stressed', 'fr'));
        $this->assertSame('Overwhelmed (Stressed)', Mood::label('overwhelmed', 'en'));
        $this->assertNull(Mood::label('from-a-newer-client', 'fr'));
    }
}
