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
use Illuminate\Support\Facades\DB;

class SyncPushService
{
    private const PRIORITY = [
        'entry' => 1,
        'quest' => 1,
        'character' => 1,
        'quote' => 1,
        'entry_attachment' => 2,
        'entry_audio' => 2,
        'entry_quest' => 3,
        'entry_character' => 3,
    ];

    /**
     * @param  array<int, array<string, mixed>>  $changes
     * @return array{confirmed: array<int, string>, conflicts: array<int, array<string, mixed>>}
     */
    public function process(User $user, array $changes): array
    {
        $confirmed = [];
        $conflicts = [];

        $sorted = $changes;
        usort($sorted, fn ($a, $b) => (self::PRIORITY[$a['entityType']] ?? 99) <=> (self::PRIORITY[$b['entityType']] ?? 99));

        DB::transaction(function () use ($user, $sorted, &$confirmed, &$conflicts) {
            foreach ($sorted as $change) {
                $result = $this->processChange($user, $change);

                if ($result['kind'] === 'confirmed') {
                    $confirmed[] = $change['entityId'];
                } elseif ($result['kind'] === 'conflict') {
                    $conflicts[] = $result['payload'];
                }
            }
        });

        return ['confirmed' => $confirmed, 'conflicts' => $conflicts];
    }

    /**
     * @param  array<string, mixed>  $change
     * @return array{kind: string, payload?: array<string, mixed>}
     */
    private function processChange(User $user, array $change): array
    {
        return match ($change['entityType']) {
            'entry' => $this->handleEntry($user, $change),
            'quest' => $this->handleQuest($user, $change),
            'character' => $this->handleCharacter($user, $change),
            'quote' => $this->handleQuote($user, $change),
            'entry_attachment' => $this->handleEntryAttachment($user, $change),
            'entry_audio' => $this->handleEntryAudio($user, $change),
            'entry_quest' => $this->handleEntryQuest($user, $change),
            'entry_character' => $this->handleEntryCharacter($user, $change),
            default => ['kind' => 'skipped'],
        };
    }

    private function handleEntry(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = Entry::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null && $existing->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'entry',
                'entityId' => $id,
                'serverVersion' => EntryResource::serialize($existing),
            ]];
        }

        $entry = $existing ?? new Entry;
        $entry->forceFill([
            'id' => $id,
            'user_id' => $user->id,
            'title' => $data['title'] ?? '',
            'html' => $data['html'] ?? '',
            'mood' => $data['mood'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'entry_date' => IsoDate::parse($data['entryDate'] ?? null),
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $entry->timestamps = false;
        $entry->save();

        return ['kind' => 'confirmed'];
    }

    private function handleQuest(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = Quest::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null && $existing->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'quest',
                'entityId' => $id,
                'serverVersion' => QuestResource::serialize($existing),
            ]];
        }

        $quest = $existing ?? new Quest;
        $quest->forceFill([
            'id' => $id,
            'user_id' => $user->id,
            'type' => $data['type'] ?? 'side',
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'status' => $data['status'] ?? 'active',
            'color' => $data['color'] ?? null,
            'icon' => $data['icon'] ?? null,
            'started_at' => IsoDate::parse($data['startedAt'] ?? null),
            'completed_at' => IsoDate::parse($data['completedAt'] ?? null),
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $quest->timestamps = false;
        $quest->save();

        return ['kind' => 'confirmed'];
    }

    private function handleCharacter(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = Character::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null && $existing->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'character',
                'entityId' => $id,
                'serverVersion' => CharacterResource::serialize($existing),
            ]];
        }

        $character = $existing ?? new Character;
        $character->forceFill([
            'id' => $id,
            'user_id' => $user->id,
            'name' => $data['name'] ?? '',
            'relationship' => $data['relationship'] ?? null,
            'note' => $data['note'] ?? '',
            'photo_uri' => '',
            'remote_photo_uri' => $data['remotePhotoUri'] ?? null,
            'color' => $data['color'] ?? null,
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $character->timestamps = false;
        $character->save();

        return ['kind' => 'confirmed'];
    }

    private function handleQuote(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = Quote::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null && $existing->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'quote',
                'entityId' => $id,
                'serverVersion' => QuoteResource::serialize($existing),
            ]];
        }

        $quote = $existing ?? new Quote;
        $quote->forceFill([
            'id' => $id,
            'user_id' => $user->id,
            'text' => $data['text'] ?? '',
            'source' => $data['source'] ?? null,
            'note' => $data['note'] ?? '',
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $quote->timestamps = false;
        $quote->save();

        return ['kind' => 'confirmed'];
    }

    private function handleEntryAttachment(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $entryId = $data['entryId'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $entryId || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = EntryAttachment::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null) {
            $owner = Entry::query()->withoutGlobalScopes()->find($existing->entry_id);
            if (! $owner || $owner->user_id !== $user->id) {
                return ['kind' => 'skipped'];
            }
        } else {
            $owner = Entry::query()->withoutGlobalScopes()->find($entryId);
            if (! $owner || $owner->user_id !== $user->id) {
                return ['kind' => 'skipped'];
            }
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'entry_attachment',
                'entityId' => $id,
                'serverVersion' => EntryAttachmentResource::serialize($existing),
            ]];
        }

        $attachment = $existing ?? new EntryAttachment;
        $attachment->forceFill([
            'id' => $id,
            'entry_id' => $entryId,
            'uri' => '',
            'remote_uri' => $existing?->remote_uri ?? ($data['remoteUri'] ?? null),
            'width' => (int) ($data['width'] ?? 0),
            'height' => (int) ($data['height'] ?? 0),
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $attachment->timestamps = false;
        $attachment->save();

        return ['kind' => 'confirmed'];
    }

    private function handleEntryAudio(User $user, array $change): array
    {
        $data = $change['data'];
        $id = $data['id'] ?? null;
        $entryId = $data['entryId'] ?? null;
        $incomingUpdatedAt = IsoDate::parse($data['updatedAt'] ?? null);

        if (! $id || ! $entryId || ! $incomingUpdatedAt) {
            return ['kind' => 'skipped'];
        }

        $existing = EntryAudio::query()->withoutGlobalScopes()->find($id);

        if ($existing !== null) {
            $owner = Entry::query()->withoutGlobalScopes()->find($existing->entry_id);
            if (! $owner || $owner->user_id !== $user->id) {
                return ['kind' => 'skipped'];
            }
        } else {
            $owner = Entry::query()->withoutGlobalScopes()->find($entryId);
            if (! $owner || $owner->user_id !== $user->id) {
                return ['kind' => 'skipped'];
            }
        }

        if ($existing !== null && $existing->updated_at->greaterThan($incomingUpdatedAt)) {
            return ['kind' => 'conflict', 'payload' => [
                'entityType' => 'entry_audio',
                'entityId' => $id,
                'serverVersion' => EntryAudioResource::serialize($existing),
            ]];
        }

        $audio = $existing ?? new EntryAudio;
        $audio->forceFill([
            'id' => $id,
            'entry_id' => $entryId,
            'uri' => '',
            'remote_uri' => $existing?->remote_uri ?? ($data['remoteUri'] ?? null),
            'duration_ms' => (int) ($data['durationMs'] ?? 0),
            'waveform' => is_array($data['waveform'] ?? null) ? $data['waveform'] : [],
            'is_deleted' => $data['isDeleted'] ?? false,
            'created_at' => IsoDate::parse($data['createdAt'] ?? null) ?? $incomingUpdatedAt,
            'updated_at' => $incomingUpdatedAt,
        ]);
        $audio->timestamps = false;
        $audio->save();

        return ['kind' => 'confirmed'];
    }

    private function handleEntryQuest(User $user, array $change): array
    {
        $data = $change['data'];
        $entryId = $data['entryId'] ?? null;
        $questId = $data['questId'] ?? null;

        if (! $entryId || ! $questId) {
            return ['kind' => 'skipped'];
        }

        $entry = Entry::query()->withoutGlobalScopes()->find($entryId);
        $quest = Quest::query()->withoutGlobalScopes()->find($questId);
        if (! $entry || $entry->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }
        if (! $quest || $quest->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        $now = now()->format('Y-m-d H:i:s.v');

        if ($change['operation'] === 'delete') {
            DB::table('entry_quests')
                ->where('entry_id', $entryId)
                ->where('quest_id', $questId)
                ->delete();

            DB::table('entry_quest_tombstones')->upsert(
                [['user_id' => $user->id, 'entry_id' => $entryId, 'quest_id' => $questId, 'deleted_at' => $now]],
                ['user_id', 'entry_id', 'quest_id'],
                ['deleted_at']
            );
        } else {
            DB::table('entry_quests')->insertOrIgnore([
                'entry_id' => $entryId,
                'quest_id' => $questId,
                'created_at' => $now,
            ]);

            DB::table('entry_quest_tombstones')
                ->where('user_id', $user->id)
                ->where('entry_id', $entryId)
                ->where('quest_id', $questId)
                ->delete();
        }

        return ['kind' => 'confirmed'];
    }

    private function handleEntryCharacter(User $user, array $change): array
    {
        $data = $change['data'];
        $entryId = $data['entryId'] ?? null;
        $characterId = $data['characterId'] ?? null;

        if (! $entryId || ! $characterId) {
            return ['kind' => 'skipped'];
        }

        $entry = Entry::query()->withoutGlobalScopes()->find($entryId);
        $character = Character::query()->withoutGlobalScopes()->find($characterId);
        if (! $entry || $entry->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }
        if (! $character || $character->user_id !== $user->id) {
            return ['kind' => 'skipped'];
        }

        $now = now()->format('Y-m-d H:i:s.v');

        if ($change['operation'] === 'delete') {
            DB::table('entry_characters')
                ->where('entry_id', $entryId)
                ->where('character_id', $characterId)
                ->delete();

            DB::table('entry_character_tombstones')->upsert(
                [['user_id' => $user->id, 'entry_id' => $entryId, 'character_id' => $characterId, 'deleted_at' => $now]],
                ['user_id', 'entry_id', 'character_id'],
                ['deleted_at']
            );
        } else {
            DB::table('entry_characters')->insertOrIgnore([
                'entry_id' => $entryId,
                'character_id' => $characterId,
                'created_at' => $now,
            ]);

            DB::table('entry_character_tombstones')
                ->where('user_id', $user->id)
                ->where('entry_id', $entryId)
                ->where('character_id', $characterId)
                ->delete();
        }

        return ['kind' => 'confirmed'];
    }
}
