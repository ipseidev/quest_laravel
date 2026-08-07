<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadFileRequest;
use App\Models\Character;
use App\Models\EntryAttachment;
use App\Models\EntryAudio;
use App\Models\EntryVideo;
use App\Services\Upload\BackupQuotaService;
use App\Services\Upload\BinaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class UploadController extends Controller
{
    private const ATTACHMENT_MIMES = [
        'image/jpeg', 'image/png', 'image/heic', 'image/heif', 'image/webp', 'image/gif',
    ];

    private const ATTACHMENT_MAX_BYTES = 25 * 1024 * 1024;

    private const AUDIO_MIMES = [
        'audio/mp4', 'audio/m4a', 'audio/aac', 'audio/mpeg', 'audio/wav', 'audio/x-wav',
    ];

    private const AUDIO_MAX_BYTES = 50 * 1024 * 1024;

    private const VIDEO_MIMES = [
        'video/mp4', 'video/quicktime', 'video/x-m4v',
    ];

    /**
     * Per-clip ceiling. Kept in step with the client's own pre-flight refusal in
     * `use-video-picker` — the client checks first so the user learns before
     * spending upload bandwidth, and this is the authority that makes the limit
     * real. Raising it here without raising `php.ini`'s `upload_max_filesize` /
     * `post_max_size` would only turn a clean 413 into a broken request.
     */
    private const VIDEO_MAX_BYTES = 300 * 1024 * 1024;

    public function __construct(
        private readonly BinaryUploadService $service,
        private readonly BackupQuotaService $quota,
    ) {}

    public function attachment(UploadFileRequest $request, string $attachmentId): JsonResponse
    {
        $attachment = EntryAttachment::query()->find($attachmentId);
        if ($attachment === null) {
            return $this->notFound();
        }
        if ($attachment->remote_uri !== null) {
            return $this->alreadyUploaded();
        }
        $file = $request->file('file');
        if (($error = $this->validateUpload($file, self::ATTACHMENT_MIMES, self::ATTACHMENT_MAX_BYTES)) !== null) {
            return $error;
        }
        if (! $this->quota->canStore($request->user(), (int) $file->getSize())) {
            return $this->quotaExceeded();
        }

        $url = $this->service->store('attachments', $request->user()->id, $attachmentId, $file);

        $attachment->remote_uri = $url;
        $attachment->size_bytes = (int) $file->getSize();
        $attachment->save();

        return response()->json(['remoteUri' => $url]);
    }

    public function audio(UploadFileRequest $request, string $audioId): JsonResponse
    {
        $audio = EntryAudio::query()->find($audioId);
        if ($audio === null) {
            return $this->notFound();
        }
        if ($audio->remote_uri !== null) {
            return $this->alreadyUploaded();
        }
        $file = $request->file('file');
        if (($error = $this->validateUpload($file, self::AUDIO_MIMES, self::AUDIO_MAX_BYTES)) !== null) {
            return $error;
        }
        if (! $this->quota->canStore($request->user(), (int) $file->getSize())) {
            return $this->quotaExceeded();
        }

        $url = $this->service->store('audio', $request->user()->id, $audioId, $file);

        $audio->remote_uri = $url;
        $audio->size_bytes = (int) $file->getSize();
        $audio->save();

        return response()->json(['remoteUri' => $url]);
    }

    public function video(UploadFileRequest $request, string $videoId): JsonResponse
    {
        $video = EntryVideo::query()->find($videoId);
        if ($video === null) {
            return $this->notFound();
        }
        if ($video->remote_uri !== null) {
            return $this->alreadyUploaded();
        }
        if (! $request->user()->hasActiveSubscription()) {
            return $this->videoRequiresPlus();
        }
        $file = $request->file('file');
        if (($error = $this->validateUpload($file, self::VIDEO_MIMES, self::VIDEO_MAX_BYTES)) !== null) {
            return $error;
        }
        // No quota check: only subscribers get past the gate above, and they are
        // unlimited. Video is excluded from the free budget by design — see
        // `BackupQuotaService`.

        $url = $this->service->store('videos', $request->user()->id, $videoId, $file);

        $video->remote_uri = $url;
        $video->size_bytes = (int) $file->getSize();
        $video->save();

        return response()->json(['remoteUri' => $url]);
    }

    public function characterPhoto(UploadFileRequest $request, string $characterId): JsonResponse
    {
        $character = Character::query()->find($characterId);
        if ($character === null) {
            return $this->notFound();
        }
        if ($character->remote_photo_uri !== null) {
            return $this->alreadyUploaded();
        }
        $file = $request->file('file');
        if (($error = $this->validateUpload($file, self::ATTACHMENT_MIMES, self::ATTACHMENT_MAX_BYTES)) !== null) {
            return $error;
        }
        if (! $this->quota->canStore($request->user(), (int) $file->getSize())) {
            return $this->quotaExceeded();
        }

        $url = $this->service->store('character-photos', $request->user()->id, $characterId, $file);

        $character->remote_photo_uri = $url;
        $character->size_bytes = (int) $file->getSize();
        $character->save();

        return response()->json(['remoteUri' => $url]);
    }

    private function validateUpload(UploadedFile $file, array $allowedMimes, int $maxBytes): ?JsonResponse
    {
        if (! in_array($file->getClientMimeType(), $allowedMimes, true)) {
            return response()->json([
                'error' => 'unsupported_media_type',
                'message' => 'The uploaded file type is not supported.',
            ], ResponseAlias::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        if ($file->getSize() > $maxBytes) {
            return response()->json([
                'error' => 'payload_too_large',
                'message' => 'The uploaded file exceeds the maximum allowed size.',
            ], ResponseAlias::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return null;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => 'not_found',
            'message' => 'Resource not found.',
        ], 404);
    }

    private function alreadyUploaded(): JsonResponse
    {
        return response()->json([
            'error' => 'already_uploaded',
            'message' => 'A binary has already been uploaded for this resource.',
        ], 409);
    }

    private function quotaExceeded(): JsonResponse
    {
        return response()->json([
            'error' => 'media_quota_exceeded',
            'message' => 'Your free cloud backup is full. Upgrade to Nacre Plus for unlimited media backup.',
        ], ResponseAlias::HTTP_PAYMENT_REQUIRED);
    }

    /**
     * Video cloud backup is a Nacre Plus feature. Recording and replaying clips is
     * fully free — they simply stay on the device. The client already declines to
     * send them (`binary-uploads.ts` gates on the entitlement so a free user never
     * spends 200 MB of mobile data to earn a refusal); this is the authority that
     * makes the rule real.
     *
     * Deliberately reuses 402 rather than 403: the client's existing handling for
     * that status is "terminal, stop re-offering this binary", which is exactly
     * the desired behaviour, and the status becomes correct again the moment the
     * user subscribes.
     */
    private function videoRequiresPlus(): JsonResponse
    {
        return response()->json([
            'error' => 'video_backup_requires_plus',
            'message' => 'Video cloud backup is part of Nacre Plus. Your clips stay on this device.',
        ], ResponseAlias::HTTP_PAYMENT_REQUIRED);
    }
}
