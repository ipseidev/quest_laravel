<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChapterResource;
use App\Models\Chapter;
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
}
