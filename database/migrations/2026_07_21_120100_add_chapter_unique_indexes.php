<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Enforce one chapter per (user, kind, period) and per (quest, kind) at the DB
     * level, closing the racy exists()-then-create() window in ChapterGenerator
     * (two concurrent jobs — e.g. a manual run alongside the scheduler — could both
     * pass the exists() check and insert duplicates). Partial indexes because the
     * columns are nullable: monthly/annual carry a period_start with quest_id NULL;
     * quest arcs carry a quest_id with period_start derived from the quest.
     */
    public function up(): void
    {
        // Dedupe defensively before adding the constraint. No-op in prod — the
        // feature ships off (QUEST_CHAPTERS_ENABLED defaults false), so there are
        // no live chapters — but this keeps the migration safe against any dev data.
        DB::statement(<<<'SQL'
            DELETE FROM chapters a USING chapters b
            WHERE a.quest_id IS NULL AND b.quest_id IS NULL AND a.kind <> 'quest'
              AND a.user_id = b.user_id AND a.kind = b.kind AND a.period_start = b.period_start
              AND (a.created_at < b.created_at OR (a.created_at = b.created_at AND a.ctid < b.ctid))
        SQL);
        DB::statement(<<<'SQL'
            DELETE FROM chapters a USING chapters b
            WHERE a.quest_id IS NOT NULL AND b.quest_id IS NOT NULL
              AND a.quest_id = b.quest_id AND a.kind = b.kind
              AND (a.created_at < b.created_at OR (a.created_at = b.created_at AND a.ctid < b.ctid))
        SQL);

        // `kind <> 'quest'` is load-bearing: chapters.quest_id has a nullOnDelete FK,
        // so hard-deleting a quest nulls its (kind='quest') chapter's quest_id and
        // would otherwise re-parent it INTO this index. Two such orphans sharing a
        // period_start would then collide during the retention purge's quest delete
        // and abort it. Excluding kind='quest' keeps orphaned quest chapters out.
        DB::statement("CREATE UNIQUE INDEX chapters_period_unique ON chapters (user_id, kind, period_start) WHERE quest_id IS NULL AND kind <> 'quest'");
        DB::statement('CREATE UNIQUE INDEX chapters_quest_unique ON chapters (quest_id, kind) WHERE quest_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS chapters_period_unique');
        DB::statement('DROP INDEX IF EXISTS chapters_quest_unique');
    }
};
