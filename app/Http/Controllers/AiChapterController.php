<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChapterResource;
use App\Jobs\GenerateSampleChapter;
use App\Models\Chapter;
use App\Models\Entry;
use App\Models\Scopes\BelongsToCurrentUserScope;
use App\Services\Chapter\ChapterGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiChapterController extends Controller
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function index(Request $request): array
    {
        // Consent gate: a user who has not opted into the AI layer sees no
        // chapters, even if some were generated while they were opted in.
        // The client already treats an empty list as "none".
        if (! $request->user()->ai_chapters_opt_in) {
            return [];
        }

        return Chapter::query()
            ->where('status', 'ready')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Chapter $chapter) => ChapterResource::serialize($chapter))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Request $request, string $id): array
    {
        // Opt-out hides existing chapters immediately — 404, matching the
        // cross-user isolation behavior (no existence leak, never 403).
        if (! $request->user()->ai_chapters_opt_in) {
            abort(404);
        }

        $chapter = Chapter::query()
            ->where('status', 'ready')
            ->findOrFail($id);

        return ChapterResource::serialize($chapter);
    }

    /**
     * Generate the ONE complimentary all-time Chapter a FREE account may request
     * — the conversion hook before Nacre Plus (MONETIZATION_PLAN P1.3). Generation
     * is asynchronous (202); the client polls `GET /ai/chapters` for the result.
     */
    public function sample(Request $request): JsonResponse
    {
        $user = $request->user();

        // Kill switch — same gate as the scheduled generation commands.
        if (! config('services.anthropic.chapters_enabled')) {
            abort(404);
        }

        if (! $user->ai_chapters_opt_in) {
            return response()->json([
                'error' => 'consent_required',
                'message' => 'Enable AI Chapters to generate your sample.',
            ], 403);
        }

        // Subscribers already get recurring Chapters — the free sample doesn't apply.
        if ($user->hasActiveSubscription()) {
            return response()->json([
                'error' => 'sample_not_applicable',
                'message' => 'Your Nacre Plus plan already includes Chapters.',
            ], 409);
        }

        // One per account: the stamp is the optimistic lock; the ready-chapter
        // check is a belt-and-suspenders guard (a free user's only chapter is
        // this sample, so having one means it was already produced).
        $alreadyHasChapter = Chapter::query()->where('status', 'ready')->exists();
        if ($user->sample_chapter_generated_at !== null || $alreadyHasChapter) {
            return response()->json([
                'error' => 'sample_already_used',
                'message' => 'You have already generated your free Chapter.',
            ], 409);
        }

        // Need enough material for an all-time Chapter, or the job would produce
        // nothing. Surface the threshold so the client can nudge ("keep writing").
        $entryCount = Entry::query()
            ->withoutGlobalScope(BelongsToCurrentUserScope::class)
            ->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->count();

        if ($entryCount < ChapterGenerator::MIN_ALLTIME_ENTRIES) {
            return response()->json([
                'error' => 'not_enough_entries',
                'message' => 'Keep writing — your first Chapter unlocks once your journal is a little fuller.',
                'required' => ChapterGenerator::MIN_ALLTIME_ENTRIES,
                'current' => $entryCount,
            ], 422);
        }

        // Optimistic lock (blocks a concurrent second request), then generate
        // asynchronously. The job releases the lock if it produces nothing.
        $user->forceFill(['sample_chapter_generated_at' => now()])->save();
        GenerateSampleChapter::dispatch($user);

        return response()->json([
            'status' => 'generating',
            'message' => 'Your Chapter is being written — it will appear shortly.',
        ], 202);
    }
}
