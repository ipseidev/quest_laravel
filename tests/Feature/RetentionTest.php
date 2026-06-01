<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private function setUpdatedAt(Model $model, \DateTimeInterface $when): void
    {
        DB::table($model->getTable())
            ->where('id', $model->getKey())
            ->update(['updated_at' => $when->format('Y-m-d H:i:s.v')]);
    }

    public function test_r1_entry_soft_deleted_more_than_30_days_is_purged(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->for($user)->create(['is_deleted' => true]);

        $this->setUpdatedAt($entry, now()->subDays(31));

        Artisan::call('quest:purge-expired');

        $this->assertDatabaseMissing('entries', ['id' => $entry->id]);
    }

    public function test_r2_entry_soft_deleted_less_than_30_days_is_kept(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->for($user)->create(['is_deleted' => true]);

        $this->setUpdatedAt($entry, now()->subDays(29));

        Artisan::call('quest:purge-expired');

        $this->assertDatabaseHas('entries', ['id' => $entry->id]);
    }

    public function test_r3_junction_tombstone_older_than_90_days_is_purged(): void
    {
        $user = User::factory()->create();

        DB::table('entry_quest_tombstones')->insert([
            'user_id' => $user->id,
            'entry_id' => (string) Str::uuid(),
            'quest_id' => (string) Str::uuid(),
            'deleted_at' => now()->subDays(91),
        ]);

        DB::table('entry_quest_tombstones')->insert([
            'user_id' => $user->id,
            'entry_id' => (string) Str::uuid(),
            'quest_id' => (string) Str::uuid(),
            'deleted_at' => now()->subDays(89),
        ]);

        Artisan::call('quest:purge-expired');

        $this->assertDatabaseCount('entry_quest_tombstones', 1);
    }

    public function test_purge_deletes_attachment_binaries_from_s3(): void
    {
        Storage::fake('s3');

        $user = User::factory()->create();
        $entry = Entry::factory()->for($user)->create();
        $attachment = EntryAttachment::factory()->for($entry)->create([
            'is_deleted' => true,
            'remote_uri' => 'https://cdn.example.com/attachments/'.$user->id.'/somefile.jpg',
        ]);

        // Create the fake file at the path the purge expects.
        $path = 'attachments/'.$user->id.'/'.$attachment->id.'.jpg';
        Storage::disk('s3')->put($path, 'fake-image-bytes');

        $this->setUpdatedAt($attachment, now()->subDays(31));

        Artisan::call('quest:purge-expired');

        Storage::disk('s3')->assertMissing($path);
        $this->assertDatabaseMissing('entry_attachments', ['id' => $attachment->id]);
    }

    public function test_purge_handles_each_content_type(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();

        $oldQuest = Quest::factory()->for($user)->create(['is_deleted' => true]);
        $oldCharacter = Character::factory()->for($user)->create(['is_deleted' => true]);
        $oldEntry = Entry::factory()->for($user)->create(['is_deleted' => true]);
        $oldAudio = EntryAudio::factory()->for($oldEntry)->create(['is_deleted' => true]);

        $this->setUpdatedAt($oldQuest, now()->subDays(31));
        $this->setUpdatedAt($oldCharacter, now()->subDays(31));
        $this->setUpdatedAt($oldEntry, now()->subDays(31));
        $this->setUpdatedAt($oldAudio, now()->subDays(31));

        $recentEntry = Entry::factory()->for($user)->create(['is_deleted' => true]);
        $this->setUpdatedAt($recentEntry, now()->subDays(5));

        Artisan::call('quest:purge-expired');

        $this->assertDatabaseMissing('quests', ['id' => $oldQuest->id]);
        $this->assertDatabaseMissing('characters', ['id' => $oldCharacter->id]);
        $this->assertDatabaseMissing('entries', ['id' => $oldEntry->id]);
        $this->assertDatabaseMissing('entry_audio', ['id' => $oldAudio->id]);
        $this->assertDatabaseHas('entries', ['id' => $recentEntry->id]);
    }

    public function test_purge_skips_active_content_even_when_old(): void
    {
        $user = User::factory()->create();
        $activeEntry = Entry::factory()->for($user)->create(['is_deleted' => false]);
        $this->setUpdatedAt($activeEntry, now()->subDays(365));

        Artisan::call('quest:purge-expired');

        $this->assertDatabaseHas('entries', ['id' => $activeEntry->id]);
    }
}
