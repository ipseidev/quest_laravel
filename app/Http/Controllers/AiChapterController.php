<?php

namespace App\Http\Controllers;

use App\Http\Resources\ChapterResource;
use App\Models\Chapter;

class AiChapterController extends Controller
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function index(): array
    {
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
    public function show(string $id): array
    {
        $chapter = Chapter::query()
            ->where('status', 'ready')
            ->findOrFail($id);

        return ChapterResource::serialize($chapter);
    }
}
