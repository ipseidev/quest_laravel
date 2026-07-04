<?php

namespace App\Services\Sync;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\EntryAttachmentResource;
use App\Http\Resources\EntryAudioResource;
use App\Http\Resources\EntryResource;
use App\Http\Resources\QuestResource;
use App\Http\Resources\QuoteResource;
use App\Models\Character;
use App\Models\Entry;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\Quest;
use App\Models\Quote;
use App\Models\User;
use App\Support\IsoDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncPullService
{
    /**
     * @return array{changes: array<int, array<string, mixed>>, serverTimestamp: string}
     */
    public function process(User $user, ?Carbon $lastPullTimestamp): array
    {
        // All ten reads below must observe ONE consistent snapshot: a push
        // committing between (say) the entries read and the attachments read
        // must not surface a child row without its parent (a cross-table
        // orphan). Postgres defaults to READ COMMITTED — a fresh snapshot per
        // statement — so a bare transaction is not enough; raise it to
        // REPEATABLE READ. Guarded to the outermost transaction: Postgres
        // rejects SET TRANSACTION ISOLATION LEVEL once a statement has run, so
        // under RefreshDatabase's wrapping test transaction (where we are only
        // a savepoint, and reads run sequentially anyway) we skip it.
        return DB::transaction(function () use ($user, $lastPullTimestamp) {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $this->collectChanges($user, $lastPullTimestamp);
        });
    }

    /**
     * @return array{changes: array<int, array<string, mixed>>, serverTimestamp: string}
     */
    private function collectChanges(User $user, ?Carbon $lastPullTimestamp): array
    {
        $serverTimestamp = now();

        $changes = [];

        // 1. Quests
        foreach ($this->quests($user, $lastPullTimestamp) as $quest) {
            $changes[] = [
                'entityType' => 'quest',
                'operation' => 'upsert',
                'data' => QuestResource::serialize($quest),
            ];
        }

        // 2. Characters
        foreach ($this->characters($user, $lastPullTimestamp) as $character) {
            $changes[] = [
                'entityType' => 'character',
                'operation' => 'upsert',
                'data' => CharacterResource::serialize($character),
            ];
        }

        // 3. Quotes
        foreach ($this->quotes($user, $lastPullTimestamp) as $quote) {
            $changes[] = [
                'entityType' => 'quote',
                'operation' => 'upsert',
                'data' => QuoteResource::serialize($quote),
            ];
        }

        // 4. Entries
        foreach ($this->entries($user, $lastPullTimestamp) as $entry) {
            $changes[] = [
                'entityType' => 'entry',
                'operation' => 'upsert',
                'data' => EntryResource::serialize($entry),
            ];
        }

        // 5. Entry attachments
        foreach ($this->entryAttachments($user, $lastPullTimestamp) as $attachment) {
            $changes[] = [
                'entityType' => 'entry_attachment',
                'operation' => 'upsert',
                'data' => EntryAttachmentResource::serialize($attachment),
            ];
        }

        // 6. Entry audio
        foreach ($this->entryAudio($user, $lastPullTimestamp) as $audio) {
            $changes[] = [
                'entityType' => 'entry_audio',
                'operation' => 'upsert',
                'data' => EntryAudioResource::serialize($audio),
            ];
        }

        // 7. entry_quest upserts
        foreach ($this->junctionRows('entry_quests', 'quest_id', $user, $lastPullTimestamp) as $row) {
            $changes[] = [
                'entityType' => 'entry_quest',
                'operation' => 'upsert',
                'data' => ['entryId' => $row->entry_id, 'questId' => $row->quest_id],
            ];
        }

        // 8. entry_quest tombstones
        foreach ($this->tombstoneRows('entry_quest_tombstones', 'quest_id', $user, $lastPullTimestamp) as $row) {
            $changes[] = [
                'entityType' => 'entry_quest',
                'operation' => 'delete',
                'data' => ['entryId' => $row->entry_id, 'questId' => $row->quest_id],
            ];
        }

        // 9. entry_character upserts
        foreach ($this->junctionRows('entry_characters', 'character_id', $user, $lastPullTimestamp) as $row) {
            $changes[] = [
                'entityType' => 'entry_character',
                'operation' => 'upsert',
                'data' => ['entryId' => $row->entry_id, 'characterId' => $row->character_id],
            ];
        }

        // 10. entry_character tombstones
        foreach ($this->tombstoneRows('entry_character_tombstones', 'character_id', $user, $lastPullTimestamp) as $row) {
            $changes[] = [
                'entityType' => 'entry_character',
                'operation' => 'delete',
                'data' => ['entryId' => $row->entry_id, 'characterId' => $row->character_id],
            ];
        }

        return [
            'changes' => $changes,
            'serverTimestamp' => IsoDate::format($serverTimestamp),
        ];
    }

    private function quests(User $user, ?Carbon $since)
    {
        return Quest::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function characters(User $user, ?Carbon $since)
    {
        return Character::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function quotes(User $user, ?Carbon $since)
    {
        return Quote::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function entries(User $user, ?Carbon $since)
    {
        return Entry::query()
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function entryAttachments(User $user, ?Carbon $since)
    {
        return EntryAttachment::query()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('entries')
                ->whereColumn('entries.id', 'entry_attachments.entry_id')
                ->where('entries.user_id', $user->id))
            ->when($since, fn ($q) => $q->where('entry_attachments.updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function entryAudio(User $user, ?Carbon $since)
    {
        return EntryAudio::query()
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('entries')
                ->whereColumn('entries.id', 'entry_audio.entry_id')
                ->where('entries.user_id', $user->id))
            ->when($since, fn ($q) => $q->where('entry_audio.updated_at', '>', $since))
            ->orderBy('updated_at')
            ->get();
    }

    private function junctionRows(string $table, string $otherColumn, User $user, ?Carbon $since)
    {
        return DB::table($table)
            ->join('entries', 'entries.id', '=', $table.'.entry_id')
            ->where('entries.user_id', $user->id)
            ->when($since, fn ($q) => $q->where($table.'.created_at', '>', $since))
            ->orderBy($table.'.created_at')
            ->select($table.'.entry_id', $table.'.'.$otherColumn, $table.'.created_at')
            ->get();
    }

    private function tombstoneRows(string $table, string $otherColumn, User $user, ?Carbon $since)
    {
        return DB::table($table)
            ->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('deleted_at', '>', $since))
            ->orderBy('deleted_at')
            ->select('entry_id', $otherColumn, 'deleted_at')
            ->get();
    }
}
